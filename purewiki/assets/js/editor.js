/**
 * PureWiki - Editor Core
 *
 * Logic for Editor.js integration and page editing. Manages the editor
 * instance, draft saving, and UI Interactions.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

let currentEditorJsInstance = null;
let currentPageData = {};
let currentGlobalConfig = {};
let publishedBlocks = [];
let normalizedPublishedString = '[]';
let isNewPage = false;
let autoSaveTimeout = null;
let hasUnsavedChanges = false;
window.pwEditorInitializing = true;
let lockHeartbeatInterval = null;
let lockedPagePath = null;
let isSavingDraft = false;


/** Cleans empty properties */
function cleanEmptyProps(obj) {
    if (Array.isArray(obj)) {
        const cleaned = obj.map(cleanEmptyProps).filter(val => val != null && (typeof val !== 'object' || Object.keys(val).length > 0));
        return cleaned.length > 0 ? cleaned : undefined;
    } else if (obj !== null && typeof obj === 'object') {
        const cleaned = Object.fromEntries(
            Object.entries(obj).map(([k, v]) => [k, cleanEmptyProps(v)])
                               .filter(([_, v]) => v != null && (typeof v !== 'object' || Object.keys(v).length > 0))
        );
        return Object.keys(cleaned).length > 0 ? cleaned : undefined;
    }
    return obj;
}

/** Normalizes Editor.js blocks */
function getNormalizedBlocks(blocks) {
    if (!blocks || !Array.isArray(blocks)) return [];

    return blocks.map(block => {
        const b = { ...block };
        if (b.tunes) {
            b.tunes = cleanEmptyProps(JSON.parse(JSON.stringify(b.tunes)));
            if (b.tunes === undefined) {
                delete b.tunes;
            }
        }
        return b;
    });
}

/** Compares content */
function hasPageContentChanged(currentBlocks) {
    if ((currentBlocks || []).length !== publishedBlocks.length) {
        return true;
    }

    const normalizedCurrentStr = JSON.stringify(getNormalizedBlocks(currentBlocks));
    const isChanged = normalizedCurrentStr !== normalizedPublishedString;

    return isChanged;
}


/**
 * Updates the editor header buttons based on draft status.
 * @param {boolean} isDraft - True if the current page has unsaved draft.
 */
function updateEditorButtons(isDraft) {
    const draftBadge = document.getElementById('pw-editor-draft-badge');
    const btnDeleteDraft = document.getElementById('pw-btn-delete-draft');
    const btnPublish = document.getElementById('pw-btn-publish');
    const btnPreview = document.getElementById('pw-btn-preview');

    if (draftBadge) draftBadge.style.display = isDraft ? 'inline-block' : 'none';
    if (btnDeleteDraft) btnDeleteDraft.style.display = isDraft ? 'inline-block' : 'none';
    if (btnPublish) btnPublish.disabled = !isDraft;
    if (btnPreview) {
        btnPreview.innerHTML = isDraft
            ? '<iconify-icon icon="mdi:eye"></iconify-icon> ' + __('editor.preview')
            : '<iconify-icon icon="mdi:eye"></iconify-icon> ' + __('editor.visit_page');
    }
}

/** Updates history button with current date. */
function updateHistoryLabel() {
    const label = document.getElementById('pw-history-label');
    if (!label) return;
    label.textContent = (currentPageData && currentPageData.DateModified)
        ? formatPwDate(new Date(currentPageData.DateModified))
        : '\u2013';
}

/** Opens the Editor view */
function openEditorForPage(pagePath, _ignored, openSettings = false) {
    if (!pagePath) return;
    if (openSettings) {
        window.location.href = (window.PW_BASE_PATH || '') + '/dashboard/page-settings?path=' + encodeURIComponent(pagePath) + '&from=dashboard';
    } else {
        window.location.href = (window.PW_BASE_PATH || '') + '/dashboard/edit?path=' + encodeURIComponent(pagePath);
    }
}

/** Initializes the Editor view */
async function openEditor(pagePath) {
    const titleEl = document.getElementById('pw-editor-title');
    if (!titleEl) return;

    titleEl.textContent = __('common.loading');

    const configResult = await apiSafe('get_config', {}, { silent: true });
    if (configResult && configResult.data) {
        currentGlobalConfig = configResult.data;
    }

    const result = await apiSafe('get_page', { path: pagePath }, { silent: true });
    if (result && result.data) {
        currentPageData = result.data;
        updateEditorButtons(result.is_draft);

        titleEl.textContent = result.data.pagetitle || pagePath.split('/').pop() || __('editor.untitled');
        titleEl.setAttribute('data-path', pagePath);


        isNewPage = result.is_draft && !result.published_data;

        publishedBlocks = result.published_data ? result.published_data.blocks : (result.is_draft ? [] : (result.data.blocks || []));
        if (!result.is_draft) {
             publishedBlocks = result.data.blocks || [];
        }

        // Cache normalized string
        normalizedPublishedString = JSON.stringify(getNormalizedBlocks(publishedBlocks));

        await initEditorJS(result.data.blocks || []);
        updateHistoryLabel();

        // Acquire page lock
        const lockAcquired = await acquirePageLock(pagePath);
        if (!lockAcquired) return;

        // Release lock on page unload
        window.addEventListener('beforeunload', () => {
            navigator.sendBeacon((window.PW_BASE_PATH || '') + '/purewiki/api.php', (() => {
                const fd = new FormData();
                fd.append('action', 'release_lock');
                fd.append('path', pagePath);
                return fd;
            })());
        });
    } else {
        const msg = result ? result.message : __('editor.failed_load_page');
        titleEl.textContent = __('editor.error_loading_page');
        await openDialog({ title: __('common.error'), text: msg, type: 'alert' });
    }
}

/**
 * Initializes Editor.js instance. Destroys existing instance if any.
 * @param {Array} blocksData The initial blocks data to load.
 * @returns {Promise} Resolves when the editor is ready and initialized.
 */
function initEditorJS(blocksData) {
    return new Promise((resolve) => {
        if (currentEditorJsInstance) {
            currentEditorJsInstance.destroy();
            currentEditorJsInstance = null;
        }

        // Shared tool config for nested editors
        const commonInlineToolbar = ['bold', 'italic', 'underline', 'marker', 'inlineCode', 'link'];
        const textSanitizer = {
            b: true,
            strong: true,
            i: true,
            em: true,
            u: true,
            mark: true,
            a: { href: true },
            code: true
        };

        window.PW_EDITOR_TOOLS = {
            paragraph: { class: Paragraph, inlineToolbar: commonInlineToolbar, sanitize: textSanitizer },
            image: ExtendedImage,
            header: {
                class: Header,
                inlineToolbar: commonInlineToolbar,
                config: { levels: [1, 2, 3, 4, 5, 6], defaultLevel: 2 },
                sanitize: textSanitizer
            },
            list: {
                class: EditorjsList,
                inlineToolbar: commonInlineToolbar,
                config: { defaultStyle: 'unordered' },
                sanitize: textSanitizer
            },
            delimiter: Delimiter,
            raw: currentGlobalConfig.editor_show_raw !== false ? RawTool : { class: RawTool, toolbox: false },
            markdown: currentGlobalConfig.editor_show_markdown !== false ? MarkdownTool : { class: MarkdownTool, toolbox: false },
            liveMarkdown: currentGlobalConfig.editor_show_liveMarkdown !== false ? LiveMarkdownTool : { class: LiveMarkdownTool, toolbox: false },
            code: currentGlobalConfig.editor_show_code !== false ? CodePrism : { class: CodePrism, toolbox: false },
            table: {
                class: Table,
                inlineToolbar: commonInlineToolbar,
                config: { rows: 2, cols: 3 },
                toolbox: currentGlobalConfig.editor_show_table !== false ? undefined : false,
                sanitize: textSanitizer
            },
            inlineCode: currentGlobalConfig.editor_show_inlineCode !== false ? { class: InlineCode } : { class: InlineCode, toolbox: false },
            underline: currentGlobalConfig.editor_show_underline !== false ? Underline : { class: Underline, toolbox: false },
            marker: currentGlobalConfig.editor_show_marker !== false ? Marker : { class: Marker, toolbox: false },
            link: {
                class: LinkAutocomplete,
                config: {
                    endpoint: (window.PW_BASE_PATH || '') + '/purewiki/api.php?action=search&format=link-autocomplete&',
                    queryParam: 'q',
                }
            },
            pagelist: currentGlobalConfig.editor_show_pagelist !== false ? PageListTool : { class: PageListTool, toolbox: false },
            toc: currentGlobalConfig.editor_show_toc !== false ? TableOfContentsTool : { class: TableOfContentsTool, toolbox: false },
            callout: currentGlobalConfig.editor_show_callout !== false ? CalloutTool : { class: CalloutTool, toolbox: false },
            block: currentGlobalConfig.editor_show_block !== false ? BlockTool : { class: BlockTool, toolbox: false },
            pageinclude: currentGlobalConfig.editor_show_pageinclude !== false ? PageIncludeTool : { class: PageIncludeTool, toolbox: false },
            snippet: currentGlobalConfig.editor_show_snippet !== false ? SnippetTool : { class: SnippetTool, toolbox: false },
        };

        window.PW_EDITOR_I18N_CONFIG = getEditorI18nConfig();

        currentEditorJsInstance = new EditorJS({
            holder: 'pw-editorjs',
            data: { blocks: blocksData },
            placeholder: 'Start writing…',
            i18n: window.PW_EDITOR_I18N_CONFIG,
            tools: {
                ...window.PW_EDITOR_TOOLS,
                accordion: currentGlobalConfig.editor_show_accordion !== false ? AccordionTool : { class: AccordionTool, toolbox: false },
                grid: currentGlobalConfig.editor_show_grid !== false ? GridTool : { class: GridTool, toolbox: false },
                paragraph: { class: Paragraph, inlineToolbar: [...commonInlineToolbar, 'textAlignInline'], sanitize: textSanitizer, tunes: ['textAlignTune', 'cssClassTune', 'duplicateBlockTune', 'hiddenBlockTune'] },
                header: { class: Header, inlineToolbar: [...commonInlineToolbar, 'textAlignInline'], config: { levels: [1, 2, 3, 4, 5, 6], defaultLevel: 2 }, sanitize: textSanitizer, tunes: ['textAlignTune', 'cssClassTune', 'duplicateBlockTune', 'hiddenBlockTune'] },
                cssClassTune: { class: CssClassTune },
                duplicateBlockTune: { class: DuplicateBlockTune },
                hiddenBlockTune: { class: HiddenBlockTune },
                textAlignTune: { class: TextAlignTune },
                textAlignInline: { class: TextAlignInlineTool },
            },
            tunes: ['cssClassTune', 'duplicateBlockTune', 'hiddenBlockTune'],
            onChange: () => {
                if (window.pwEditorInitializing) return;
                clearTimeout(autoSaveTimeout);
                autoSaveTimeout = setTimeout(async () => {
                    const outputData = await currentEditorJsInstance.save();
                    const isChanged = hasPageContentChanged(outputData.blocks);

                    if (isChanged) {
                        hasUnsavedChanges = true;
                        saveCurrentDraft(true);
                    } else {
                        const draftBadgeVisible = document.getElementById('pw-editor-draft-badge')?.style.display !== 'none';
                        if ((hasUnsavedChanges || draftBadgeVisible) && !isNewPage) {
                            const titleEl = document.getElementById('pw-editor-title');
                            const path = titleEl ? titleEl.getAttribute('data-path') : null;
                            if (path) {
                                await apiSafe('delete_draft', { path }, { silent: true });
                            }
                        }
                        hasUnsavedChanges = false;
                        updateEditorButtons(false);
                    }
                }, 1000); // 1-second debounce
            },
            onReady: () => {
                new DragDrop(currentEditorJsInstance, "3px dashed #007bff");
                setTimeout(() => {
                    window.pwEditorInitializing = false;
                    const draftBadgeVisible = document.getElementById('pw-editor-draft-badge')?.style.display !== 'none';
                    hasUnsavedChanges = draftBadgeVisible;
                    updateEditorButtons(draftBadgeVisible);
                    resolve(currentEditorJsInstance);
                }, 1000); // 1-second delay
            }
        });

        // Unsaved changes tracking for title and description
        const titleEl = document.getElementById('pw-editor-title');
        if (titleEl) {
            titleEl.addEventListener('input', () => {
                hasUnsavedChanges = true;
                clearTimeout(autoSaveTimeout);
                autoSaveTimeout = setTimeout(() => {
                    saveCurrentDraft(true);
                }, 1000);
            });
        }

        // Reset flag after init
        hasUnsavedChanges = false;
    });
}

/** Closes the Editor view and returns to the dashboard. */
function closeEditorView() {
    window.location.href = (window.PW_BASE_PATH || '') + '/dashboard';
}



/**
 * Sends a lock API request for the given action and path.
 * @param {string} action - acquire_lock | release_lock | refresh_lock
 * @param {string} path
 * @returns {Promise<object>}
 */
async function lockRequest(action, path) {
    return apiCall(action, { path });
}

/**
 * Acquires the page lock. Returns true if acquired, false if locked by someone else.
 * @param {string} path
 * @returns {Promise<boolean>}
 */
async function acquirePageLock(path) {
    try {
        const result = await lockRequest('acquire_lock', path);
        if (result.success) {
            lockedPagePath = path;
            startLockHeartbeat(path);
            return true;
        }
        showLockOverlay(result.locked_by || __('editor.another_user'), result.locked_until || 0);
        return false;
    } catch {
        // Network error — let edit anyway
        return true;
    }
}

/**
 * Releases the page lock for the given path.
 * @param {string} path
 */
async function releasePageLock(path) {
    stopLockHeartbeat();
    lockedPagePath = null;
    try { await lockRequest('release_lock', path); } catch { /* ignore */ }
}

/**
 * Starts a heartbeat that refreshes lock every 60 seconds
 * Inactivity for 20 minutes causes server lock to expire.
 * @param {string} path
 */
function startLockHeartbeat(path) {
    stopLockHeartbeat();
    lockHeartbeatInterval = setInterval(async () => {
        try { await lockRequest('refresh_lock', path); } catch { /* ignore */ }
    }, 60 * 1000);
}

/** Stops the lock heartbeat timer. */
function stopLockHeartbeat() {
    if (lockHeartbeatInterval) {
        clearInterval(lockHeartbeatInterval);
        lockHeartbeatInterval = null;
    }
}

/**
 * Shows a full-page overlay indicating the page is locked by another user.
 * @param {string} lockedBy - Username who holds the lock.
 * @param {number} lockedUntil - Unix timestamp when the lock expires.
 */
function showLockOverlay(lockedBy, lockedUntil) {
    const until = lockedUntil ? new Date(lockedUntil * 1000).toLocaleTimeString() : '—';
    const overlay = document.createElement('div');
    overlay.id = 'pw-lock-overlay';
    overlay.className = 'pw-lock-overlay';
    overlay.innerHTML = `
        <iconify-icon icon="mdi:lock"></iconify-icon>
        <h2>${__('editor.page_locked')}</h2>
        <p>
            ${__('editor.page_locked_by', lockedBy)}<br>
            ${__('editor.lock_expires', until)}
        </p>
        <button class="pw-btn" onclick="history.back()"><iconify-icon icon="mdi:arrow-left"></iconify-icon> ${__('common.back')}</button>
    `;
    document.body.appendChild(overlay);
}


/**
 * Saves the current Editor.js content as a draft.
 * @param {boolean} isAutoSave true = no toast notification
 * @param {boolean} redirectAfterSave enable toast after redirect
 */
async function saveCurrentDraft(isAutoSave = false, redirectAfterSave = false) {
    if (!currentEditorJsInstance || window.pwEditorInitializing || !hasUnsavedChanges || isSavingDraft) {
        return !isSavingDraft;
    }

    isSavingDraft = true;

    if (autoSaveTimeout) {
        clearTimeout(autoSaveTimeout);
        autoSaveTimeout = null;
    }

    const titleEl = document.getElementById('pw-editor-title');
    const path = titleEl ? titleEl.getAttribute('data-path') : null;
    if (!path) {
        isSavingDraft = false;
        return false;
    }

    const outputData = await currentEditorJsInstance.save();
    const title = titleEl.textContent !== 'Loading...' && !titleEl.querySelector('input') ? titleEl.textContent : null;

    const params = { path };
    if (title) params.title = title;
    params.blocks = JSON.stringify(outputData.blocks);

    const result = await apiSafe('save_draft', params, { silent: isAutoSave });

    if (result) {
        hasUnsavedChanges = false;  
        updateEditorButtons(true);

        if (!isAutoSave) {
            if (redirectAfterSave) {
                sessionStorage.setItem('pw-notify', JSON.stringify({text: __('editor.draft_saved'), type: 'success'}));
            } else {
                notify(__('editor.draft_saved'), 'success', 3000);
            }
        }
    } else if (!isAutoSave) {
        notify(__('editor.error_save_draft'), 'error');
    }

    isSavingDraft = false;
    return !!result;
}

/** Publishes current draft as live page. */
async function publishPage() {
    const titleEl = document.getElementById('pw-editor-title');
    const path = titleEl ? titleEl.getAttribute('data-path') : null;
    if (!path) return;

    const saved = await saveCurrentDraft();
    if (!saved) return;

    const result = await apiSafe('publish_page', { path });

    if (result) {
        updateEditorButtons(false);
        updateHistoryLabel();

        if (autoSaveTimeout) {
            clearTimeout(autoSaveTimeout);
            autoSaveTimeout = null;
        }
        hasUnsavedChanges = false;

        if (currentEditorJsInstance) {
            const outputData = await currentEditorJsInstance.save();
            publishedBlocks = outputData.blocks;
            normalizedPublishedString = JSON.stringify(getNormalizedBlocks(publishedBlocks));
        }

        notify(__('editor.page_published'), 'success');
    } else {
        notify(__('editor.error_publish_page'), 'error');
    }
}

/** Loads page history and fills the dropdown. */
async function loadPageHistory() {
    const titleEl = document.getElementById('pw-editor-title');
    const path = titleEl ? titleEl.getAttribute('data-path') : null;
    const menu = document.getElementById('pw-history-menu');
    if (!path || !menu) return;

    menu.innerHTML = '<div class="pw-history-empty">' + __('common.loading') + '</div>';

    const result = await apiSafe('get_page_history', { path }, { silent: true });
    menu.innerHTML = '';

    if (result && result.data && result.data.length > 0) {
        result.data.forEach(v => {
            const d = new Date(v.timestamp * 1000);
            const dateStr = formatPwDate(d, false);
            const timeStr = String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');

            const btn = document.createElement('button');
            btn.className = 'pw-history-item';
            
            let authorHtml = '';
            if (v.author) {
                authorHtml = `<span class="pw-history-author">${__('editor.history_by', v.author)}</span>`;
            }

            btn.innerHTML = `
                <div class="pw-history-item-meta">
                    <span class="pw-history-date">${dateStr}</span>
                    <span class="pw-history-time">${timeStr}</span>
                </div>
                ${authorHtml}
            `;
            btn.addEventListener('click', () => restorePageVersion(v.file));
            menu.appendChild(btn);
        });
    } else {
        menu.innerHTML = '<div class="pw-history-empty">' + __('editor.no_history') + '</div>';
    }
}

/**
 * Restores historical page version as a draft
 * @param {string} file filename to restore
 */
async function restorePageVersion(file) {
    const titleEl = document.getElementById('pw-editor-title');
    const path = titleEl ? titleEl.getAttribute('data-path') : null;
    if (!path) return;

    const menu = document.getElementById('pw-history-menu');
    if (menu) menu.classList.remove('pw-show');

    const confirmed = await openDialog({
        title: __('editor.restore_version_title'),
        text: __('editor.restore_version_confirm'),
        type: 'confirm',
        confirmText: __('editor.restore'),
        cancelText: __('common.cancel')
    });
    if (!confirmed) return;

    const result = await apiSafe('restore_page_version', { path, file });
    if (result) {
        notify(__('editor.version_restored'), 'success');
        await openEditor(path);
    } else {
        notify(__('editor.error_restore_version'), 'error');
    }
}

/** Deletes current draft. */
async function deleteDraft() {
    const titleEl = document.getElementById('pw-editor-title');
    const path = titleEl ? titleEl.getAttribute('data-path') : null;
    if (!path) return;

    const confirmed = await openDialog({
        title: __('editor.delete_draft_title'),
        text: __('editor.delete_draft_confirm'),
        type: 'confirm',
        confirmText: __('common.delete'),
        cancelText: __('common.cancel')
    });

    if (!confirmed) return;

    const result = await apiSafe('delete_draft', { path });

    if (result) {
        if (isNewPage) {
            sessionStorage.setItem('pw-notify', JSON.stringify({text: __('editor.draft_deleted'), type: 'success'}));
            closeEditorView();
        } else {
            await openEditor(path);
            notify(__('editor.draft_deleted'), 'success');
        }
    } else {
        notify(__('editor.error_delete_draft'), 'error');
    }
}



/** Initializes Editor UI bindings. */
function initEditorInteractions() {
    bindEditorBackButton();
    bindPageSettingsPanel();
    bindPublishAndPreviewButtons();
    bindDeleteDraftAction();
    bindEditorHistoryMenu();
    bindEditorShortcuts();
    bindTitleInlineEditing();
}

/** Back button logic. */
function bindEditorBackButton() {
    const btnBack = document.getElementById('pw-btn-back-to-dash');
    if (!btnBack) return;

    btnBack.removeAttribute('onclick');
    btnBack.onclick = async (e) => {
        e.preventDefault();

        if (currentEditorJsInstance) {
            const outputData = await currentEditorJsInstance.save();
            const isChanged = hasPageContentChanged(outputData.blocks);

            if (isChanged) {
                hasUnsavedChanges = true;
                await saveCurrentDraft(false, true);
            } else {
                // remove draft if content matches published version
                const draftBadgeVisible = document.getElementById('pw-editor-draft-badge')?.style.display !== 'none';
                if ((hasUnsavedChanges || draftBadgeVisible) && !isNewPage) {
                    const titleEl = document.getElementById('pw-editor-title');
                    const path = titleEl ? titleEl.getAttribute('data-path') : null;
                    if (path) {
                        await apiSafe('delete_draft', { path }, { silent: true });
                    }
                }
            }
        }

        if (lockedPagePath) await releasePageLock(lockedPagePath);
        closeEditorView();
    };
}

/** Page settings panel logic. */
function bindPageSettingsPanel() {
    const btnPageSettings = document.getElementById('pw-btn-page-settings');
    if (btnPageSettings) {
        btnPageSettings.addEventListener('click', async () => {
            const titleEl = document.getElementById('pw-editor-title');
            const path = titleEl ? titleEl.getAttribute('data-path') : null;
            if (!path) return;

            // Auto-save draft before leaving
            if (typeof saveCurrentDraft === 'function') {
                await saveCurrentDraft(true);
            }

            window.location.href = (window.PW_BASE_PATH || '') + '/dashboard/page-settings?path=' + encodeURIComponent(path) + '&from=editor';
        });
    }
}

/** Publish & preview button logic. */
function bindPublishAndPreviewButtons() {
    const btnPublish = document.getElementById('pw-btn-publish');
    const btnPreview = document.getElementById('pw-btn-preview');
    const titleEl = document.getElementById('pw-editor-title');

    if (btnPublish) {
        btnPublish.addEventListener('click', publishPage);
    }

    if (btnPreview) {
        btnPreview.addEventListener('click', async () => {
            if (hasUnsavedChanges) {
                await saveCurrentDraft(true);
            }

            const path = titleEl ? titleEl.getAttribute('data-path') : null;
            if (path) {
                const appRoot = window.location.pathname.split('/dashboard/')[0];
                const fullPath = appRoot + (path.startsWith('/') ? '' : '/') + path;
                const previewUrl = fullPath + (fullPath.includes('?') ? '&' : '?') + 'preview=1';

                window.open(previewUrl, '_blank');
            }
        });
    }
}

/** Delete draft logic. */
function bindDeleteDraftAction() {
    const btnDeleteDraft = document.getElementById('pw-btn-delete-draft');
    if (btnDeleteDraft) {
        btnDeleteDraft.addEventListener('click', deleteDraft);
    }
}

/** Page history dropdown logic. */
function bindEditorHistoryMenu() {
    const btnHistory = document.getElementById('pw-btn-history');
    const historyMenu = document.getElementById('pw-history-menu');

    if (btnHistory && historyMenu) {
        btnHistory.addEventListener('click', (e) => {
            e.stopPropagation();
            const isOpen = historyMenu.classList.contains('pw-show');
            if (isOpen) {
                historyMenu.classList.remove('pw-show');
            } else {
                loadPageHistory();
                historyMenu.classList.add('pw-show');
            }
        });

        document.addEventListener('click', (e) => {
            if (!e.target.closest('#pw-history-dropdown')) {
                historyMenu.classList.remove('pw-show');
            }
        });
    }
}

/** Keyboard shortcuts. */
function bindEditorShortcuts() {
    // Ctrl+S
    document.addEventListener('keydown', async (e) => {
        if ((e.ctrlKey || e.metaKey) && e.key === 's') {
            e.preventDefault();
            await saveCurrentDraft();
        }
    });
}

/** Inline title editing logic. */
function bindTitleInlineEditing() {
    const titleEl = document.getElementById('pw-editor-title');
    if (!titleEl) return;

    titleEl.addEventListener('dblclick', function() {
        if (this.querySelector('input')) return;

        const currentTitle = this.textContent;
        const input = document.createElement('input');
        input.type = 'text';
        input.value = currentTitle;
        input.className = 'pw-input pw-inline-edit-input';
        input.style.fontSize = 'inherit';
        input.style.fontWeight = 'inherit';
        input.style.padding = '0';
        input.style.margin = '0';
        input.style.border = '1px solid var(--pw-primary)';
        input.style.background = 'var(--pw-bg)';
        input.style.color = 'var(--pw-text)';
        input.style.width = '100%';

        this.textContent = '';
        this.appendChild(input);
        input.focus();

        const saveTitle = async () => {
            const newTitle = input.value.trim();
            if (newTitle === '') {
                this.textContent = currentTitle;
            } else if (newTitle !== currentTitle) {
                this.textContent = newTitle;
                const success = await saveCurrentDraft();
                if (!success) {
                    this.textContent = currentTitle;
                }
            } else {
                this.textContent = currentTitle;
            }
        };

        input.addEventListener('blur', saveTitle);
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                input.blur();
            }
            if (e.key === 'Escape') {
                this.textContent = currentTitle;
            }
        });
    });
}

// Bind interactions on load
document.addEventListener('DOMContentLoaded', initEditorInteractions);

/**
 * Initializes and opens the custom Image Selection Dialog
 * @param {Object} imageToolInstance active ExtendedImage tool
 */
function openImageSelectionDialog(imageToolInstance) {
    const overlay = document.getElementById('pw-image-dialog-overlay');
    const cancelBtn = document.getElementById('pw-image-dialog-cancel');
    const scopeSelect = document.getElementById('pw-image-scope');
    const dropZone = document.getElementById('pw-image-upload-zone');
    const fileInput = document.getElementById('pw-image-upload-input');
    const grid = document.getElementById('pw-image-grid');
    const manualPathInput = document.getElementById('pw-image-manual-path');
    const manualPathBtn = document.getElementById('pw-image-btn-manual');

    if (!overlay) {
        notify('Image dialog UI not found', 'error');
        return;
    }

    // Extract current page from URL
    const urlParams = new URLSearchParams(window.location.search);
    const currentPagePath = urlParams.get('path') || '/';

    async function loadImages() {
        const scope = scopeSelect.value;
        const targetPath = scope === 'current' ? currentPagePath : '__global__';
        grid.innerHTML = '<p style="padding:10px; color:var(--pw-text-muted);">Loading...</p>';

        const formData = new FormData();
        formData.append('action', 'list_media');
        formData.append('path', targetPath);

        try {
            const res = await fetch((window.PW_BASE_PATH || '') + '/purewiki/api.php', { method: 'POST', body: formData });
            const result = await res.json();

            grid.innerHTML = '';
            if (result.success && result.data.length > 0) {
                const images = result.data.filter(f => f.type === 'image');

                if (images.length === 0) {
                    grid.innerHTML = '<p style="padding:10px; color:var(--pw-text-muted);">No images found here.</p>';
                    return;
                }

                images.forEach(f => {
                    const card = document.createElement('div');
                    card.style.cssText = 'border: 1px solid var(--pw-border); border-radius: 4px; overflow: hidden; cursor: pointer; transition: transform 0.2s; position: relative; background: var(--pw-bg-panel); display: flex; flex-direction: column;';

                    const imgContainer = document.createElement('div');
                    imgContainer.style.cssText = 'width: 100%; height: 100px; display: flex; align-items: center; justify-content: center; background: #000;';

                    const img = document.createElement('img');
                    const basePath = targetPath === '__global__' ? '/pages/' : '/pages' + (targetPath === '/' ? '' : targetPath) + '/';
                    const imgUrl = basePath + f.name;

                    img.src = (window.PW_BASE_PATH || '') + imgUrl;
                    img.style.cssText = 'max-width: 100%; max-height: 100%; object-fit: contain;';

                    imgContainer.appendChild(img);

                    const label = document.createElement('div');
                    label.style.cssText = 'padding: 5px; font-size: 11px; text-overflow: ellipsis; white-space: nowrap; overflow: hidden; text-align: center; color: var(--pw-text-muted); border-top: 1px solid var(--pw-border);';
                    label.textContent = f.name;

                    card.appendChild(imgContainer);
                    card.appendChild(label);

                    card.addEventListener('mouseenter', () => card.style.transform = 'scale(1.03)');
                    card.addEventListener('mouseleave', () => card.style.transform = 'scale(1)');

                    card.addEventListener('click', () => {
                        imageToolInstance.onImageSelected(imgUrl);
                        closeModal();
                    });

                    grid.appendChild(card);
                });
            } else {
                grid.innerHTML = '<p style="padding:10px; color:var(--pw-text-muted);">No images found.</p>';
            }
        } catch (e) {
            grid.innerHTML = '<p style="padding:10px; color:var(--pw-text-muted);">Failed to load media.</p>';
        }
    }

    async function handleUpload(filesList, overwrite = false) {
        const files = Array.from(filesList);
        if (!files || files.length === 0) return;
        
        if (!overwrite && typeof fileInput !== 'undefined' && fileInput) {
            fileInput.value = '';
        }

        const scope = scopeSelect.value;
        const targetPath = scope === 'current' ? currentPagePath : '__global__';

        const formData = new FormData();
        formData.append('action', 'upload_media');
        formData.append('path', targetPath);
        if (overwrite) {
            formData.append('overwrite', 'true');
        }
        for (let i = 0; i < files.length; i++) {
            formData.append('files[]', files[i]);
        }

        notify(`Uploading ${files.length} file(s)...`, 'info');

        try {
            const res = await fetch((window.PW_BASE_PATH || '') + '/purewiki/api.php', { method: 'POST', body: formData });
            const result = await res.json();

            if (result.require_confirmation) {
                const confirmMsg = __('media.file_exists_confirm', result.existing_files.join(', '));
                const isConfirmed = await openDialog({
                    title: __('media.file_exists_title'),
                    text: confirmMsg,
                    type: 'confirm',
                    confirmText: __('common.confirm'),
                    cancelText: __('common.cancel')
                });

                if (isConfirmed) {
                    handleUpload(files, true);
                }
            } else if (result.success) {
                notify(result.message, 'success');
                loadImages();
            } else {
                notify(result.message, 'error');
            }
        } catch (e) {
            notify('Upload failed due to network error.', 'error');
        }
    }

    scopeSelect.onchange = () => {
        loadImages();
    };
    function closeModal() {
        overlay.classList.remove('pw-show');
        // Clean up events
        cancelBtn.onclick = null;
        dropZone.onclick = null;
        dropZone.ondragover = null;
        dropZone.ondragleave = null;
        dropZone.ondrop = null;
        fileInput.onchange = null;
        scopeSelect.onchange = null;
        if (manualPathBtn) manualPathBtn.onclick = null;
        if (manualPathInput) manualPathInput.onkeydown = null;
    }

    cancelBtn.onclick = () => closeModal();

    // Manual Path Add
    if (manualPathBtn && manualPathInput) {
        manualPathInput.value = '';

        const applyManualPath = () => {
            const val = manualPathInput.value.trim();
            if (val) {
                imageToolInstance.onImageSelected(val);
                closeModal();
            }
        };

        manualPathBtn.onclick = applyManualPath;
        manualPathInput.onkeydown = (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                applyManualPath();
            }
        };
    }

    dropZone.onclick = () => fileInput.click();
    fileInput.onchange = (e) => handleUpload(e.target.files);

    dropZone.ondragover = (e) => { e.preventDefault(); dropZone.style.background = 'var(--pw-border)'; };
    dropZone.ondragleave = (e) => { e.preventDefault(); dropZone.style.background = 'transparent'; };
    dropZone.ondrop = (e) => {
        e.preventDefault();
        dropZone.style.background = 'transparent';
        if (e.dataTransfer && e.dataTransfer.files) {
            handleUpload(e.dataTransfer.files);
        }
    };

    overlay.classList.add('pw-show');
    scopeSelect.value = 'current';
    loadImages();
}

/**
 * Returns Editor.js i18n configuration object
 */
function getEditorI18nConfig() {
    return {
        messages: {
            ui: {
                "inlineToolbar": {
                    "converter": { "Convert to": __('editor.ui_convert_to'), "Convert To": __('editor.ui_convert_to') }
                },
                "blockTunes": {
                    "toggler": { "Click to tune": __('editor.ui_click_to_tune'), "or drag to move": " " }
                },
                "toolbar": {
                    "toolbox": { "Add": __('editor.ui_add'), "Filter": __('editor.ui_filter'), "Nothing found": __('editor.ui_nothing_found') }
                },
                "popover": {
                    "Filter": __('editor.ui_filter'), "Nothing found": __('editor.ui_nothing_found'), "Convert to": __('editor.ui_convert_to'), "Convert To": __('editor.ui_convert_to'), "Add": __('editor.ui_add')
                }
            },
            toolNames: {
                "Text": __('editor.tool_paragraph'), "Heading": __('editor.tool_heading'), "List": __('editor.tool_list'), "Quote": __('editor.tool_quote'),
                "Delimiter": __('editor.tool_delimiter'), "Raw HTML": __('editor.tool_raw'), "Table": __('editor.tool_table'), "Code": __('editor.tool_code'),
                "Inline Code": __('editor.tool_inlinecode'), "Image": __('editor.tool_image'), "Link": __('editor.tool_link'), "Marker": __('editor.tool_marker'),
                "Bold": __('editor.tool_bold'), "Italic": __('editor.tool_italic'), "Underline": __('editor.tool_underline'), "Strikethrough": __('editor.tool_strikethrough'),
                "Markdown": __('editor.tool_markdown'), "Live Markdown": __('editor.tool_livemarkdown'), "Page List": __('editor.tool_pagelist'), "Table of Contents": __('editor.tool_toc'),
                "Callout": __('editor.tool_callout'), "Container": __('editor.tool_block'), "Page Include": __('editor.tool_pageinclude'), "Snippet": __('editor.tool_snippet'),
                "Accordion": __('editor.tool_accordion'), "Grid": __('editor.tool_grid'), "textAlign": __('plugins.text_align'), "Alignment": __('plugins.text_align')
            },
            blockTunes: {
                "delete": { "Delete": __('editor.ui_delete') }, "moveUp": { "Move up": __('editor.ui_move_up') }, "moveDown": { "Move down": __('editor.ui_move_down') },
                "cssClassTune": { "CSS Class": "CSS Class" }, "duplicateBlockTune": { "Duplicate": __('editor.tune_duplicate') },
                "hiddenBlockTune": { "Hidden": __('plugins.hidden_block') },
                "textAlignTune": { "Align left": __('editor.tune_text_align_left'), "Align center": __('editor.tune_text_align_center'), "Align right": __('editor.tune_text_align_right'), "Justify": __('editor.tune_text_align_justify') }
            },
            tools: {
                "link": { "Add a link": __('editor.ui_link_autocomplete_placeholder'), "Nothing found": __('editor.ui_link_autocomplete_not_found') },
                "header": { "Heading 1": __('editor.tool_heading_1'), "Heading 2": __('editor.tool_heading_2'), "Heading 3": __('editor.tool_heading_3'), "Heading 4": __('editor.tool_heading_4'), "Heading 5": __('editor.tool_heading_5'), "Heading 6": __('editor.tool_heading_6') }
            }
        }
    };
}
