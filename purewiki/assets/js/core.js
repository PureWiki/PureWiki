/**
 * PureWiki - Dashboard Core
 *
 * Main frontend logic for the dashboard and tree view. Handles API
 * communication, sidebar interactions, and custom dialog management
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

/**
 * Sends a POST request to the PureWiki API
 * @param {string} action The API action name
 * @param {Object} [params={}] Key-value pairs to send as form data
 * @returns {Promise<Object>} Parsed JSON response
 */
async function apiCall(action, params = {}) {
    const fd = new FormData();
    fd.append('action', action);
    for (const [key, val] of Object.entries(params)) {
        fd.append(key, val);
    }
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const headers = {};
    if (csrfToken) {
        headers['X-CSRF-Token'] = csrfToken;
    }
    const res = await fetch((window.PW_BASE_PATH || '') + '/purewiki/api.php', { 
        method: 'POST', 
        headers: headers,
        body: fd 
    });
    return res.json();
}

/**
 * Wrapper for API calls with automatic error handling
 * @param {string} action - API action name
 * @param {Object} [params={}] - Parameters to send
 * @param {Object} [options={}] - Options
 * @param {boolean} [options.silent=false] - Suppress error notification
 * @returns {Promise<Object|null>} Result data or null on failure
 */
async function apiSafe(action, params = {}, options = {}) {
    try {
        const result = await apiCall(action, params);
        if (!result.success) {
            if (window.PW_DEBUG) {
                console.warn('API Error:', action, result.message || '');
                notify(result.message || __('common.operation_failed'), 'error');
            } else if (!options.silent) {
                notify(result.message || __('common.operation_failed'), 'error');
            }
            return null;
        }

        if (window.PW_DEBUG) {
            console.log('API Success:', action, result.message || '');
        }

        return result;
    } catch (err) {
        if (window.PW_DEBUG) {
            console.warn('API Error (Network/Exception):', action, err);
            notify(__('common.network_error'), 'error');
        } else if (!options.silent) {
            notify(__('common.network_error'), 'error');
        }
        console.error(`API ${action} failed:`, err);
        return null;
    }
}

/**
 * Formats a Date object into a DD.MM.YYYY string
 * @param {Date} date
 * @param {boolean} [includeTime=true]
 * @returns {string}
 */
function formatPwDate(date, includeTime = true) {
    if (!(date instanceof Date) || isNaN(date)) return '—';
    const day = String(date.getDate()).padStart(2, '0');
    const mon = String(date.getMonth() + 1).padStart(2, '0');
    const year = date.getFullYear();
    let str = `${day}.${mon}.${year}`;
    if (includeTime) {
        const hours = String(date.getHours()).padStart(2, '0');
        const mins = String(date.getMinutes()).padStart(2, '0');
        str += `, ${hours}:${mins}`;
    }
    return str;
}
/** Initializes the custom dialog */
function initDialogSystem() {
    if (document.getElementById('pw-dialog-overlay')) return;

    const overlay = document.createElement('div');
    overlay.id = 'pw-dialog-overlay';
    overlay.className = 'pw-dialog-overlay';

    overlay.innerHTML = `
        <div class="pw-dialog-box">
            <h3 id="pw-dialog-title" class="pw-dialog-title"></h3>
            <div id="pw-dialog-text" class="pw-dialog-text"></div>
            <div id="pw-dialog-input-container" style="display: none;">
                <input id="pw-dialog-input" class="pw-input" type="text">
            </div>
            <div id="pw-dialog-layout-container" style="display: none; margin-top: 15px;">
                <label style="display: block; margin-bottom: 5px; font-size: 0.9rem;">${__('common.layout')}</label>
                <select id="pw-dialog-layout-select" class="pw-input" style="width: 100%;"></select>
            </div>
            <div class="pw-dialog-actions">
                <button id="pw-dialog-btn-cancel" class="pw-btn pw-btn-secondary">${__('common.cancel')}</button>
                <button id="pw-dialog-btn-confirm" class="pw-btn pw-btn-primary">${__('common.ok')}</button>
            </div>
        </div>
    `;

    document.body.appendChild(overlay);

    overlay.addEventListener('mousedown', (e) => {
        if (e.target === overlay) {
            closeDialog(null);
        }
    });
}

let currentDialogResolve = null;

/**
 * Opens a custom dialog box
 * 
 * @param {Object} options - Configuration options for the dialog
 * @param {string} options.title - Title of the dialog
 * @param {string} [options.html] - HTML content inside the dialog
 * @param {string} [options.text] - Text inside the dialog
 * @param {string} [options.type='alert'] - Type of dialog: 'alert', 'confirm', or 'prompt'
 * @param {string} [options.confirmText='OK'] - Confirmation Text
 * @param {string} [options.cancelText='Cancel'] - Cancellation Text
 * @param {string} [options.placeholder=''] - Placeholder for input if type is 'prompt'
 * @returns {Promise<any>} Promise that resolves to true/false for 'confirm', entered string or null for 'prompt', undefined for 'alert'
 */
function openDialog(options) {
    if (!document.getElementById('pw-dialog-overlay')) {
        initDialogSystem();
    }

    return new Promise((resolve) => {
        currentDialogResolve = resolve;

        const overlay = document.getElementById('pw-dialog-overlay');
        
        // Add custom class if provided
        overlay.className = 'pw-dialog-overlay';
        if (options.className) {
            overlay.classList.add(options.className);
        }

        const titleEl = document.getElementById('pw-dialog-title');
        const textEl = document.getElementById('pw-dialog-text');
        const inputContainer = document.getElementById('pw-dialog-input-container');
        const inputEl = document.getElementById('pw-dialog-input');
        const layoutContainer = document.getElementById('pw-dialog-layout-container');
        const layoutSelect = document.getElementById('pw-dialog-layout-select');
        const cancelBtn = document.getElementById('pw-dialog-btn-cancel');
        const confirmBtn = document.getElementById('pw-dialog-btn-confirm');

        titleEl.textContent = options.title || 'Dialog';

        if (options.html) {
            textEl.innerHTML = options.html;
            textEl.style.display = 'block';
        } else if (options.text) {
            textEl.textContent = options.text;
            textEl.style.display = 'block';
        } else {
            textEl.style.display = 'none';
        }

        confirmBtn.textContent = options.confirmText || 'OK';
        cancelBtn.textContent = options.cancelText || 'Cancel';

        const type = options.type || 'alert';

        if (type === 'alert') {
            cancelBtn.style.display = 'none';
            inputContainer.style.display = 'none';
            layoutContainer.style.display = 'none';
        } else if (type === 'confirm') {
            cancelBtn.style.display = 'inline-block';
            inputContainer.style.display = 'none';
            layoutContainer.style.display = 'none';
        } else if (type === 'prompt') {
            cancelBtn.style.display = 'inline-block';
            inputContainer.style.display = 'block';
            inputEl.value = options.defaultValue || '';
            inputEl.placeholder = options.placeholder || '';

            if (options.showLayout && window.pwAvailableLayouts) {
                layoutContainer.style.display = 'block';
                layoutSelect.innerHTML = '';
                window.pwAvailableLayouts.forEach(layout => {
                    const opt = document.createElement('option');
                    opt.value = layout.slug;
                    opt.textContent = layout.label;
                    if (layout.slug === 'page') opt.selected = true;
                    layoutSelect.appendChild(opt);
                });
            } else {
                layoutContainer.style.display = 'none';
            }
        }

        const newCancelBtn = cancelBtn.cloneNode(true);
        cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);

        const newConfirmBtn = confirmBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);

        newCancelBtn.addEventListener('click', () => {
            closeDialog(type === 'prompt' ? null : false);
        });

        newConfirmBtn.addEventListener('click', () => {
            if (type === 'prompt') {
                const layoutVal = document.getElementById('pw-dialog-layout-select').value;
                closeDialog({
                    value: inputEl.value,
                    layout: layoutVal
                });
            } else {
                closeDialog(true);
            }
        });

        if (type === 'prompt') {
            inputEl.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && inputEl.value.trim() !== '') {
                    closeDialog({
                        value: inputEl.value,
                        layout: layoutSelect.value
                    });
                } else if (e.key === 'Escape') {
                    closeDialog(null);
                }
            });
        }

        // Handle Escape key globally while dialog is open
        const escapeHandler = (e) => {
            if (e.key === 'Escape') {
                closeDialog(type === 'prompt' ? null : false);
                document.removeEventListener('keydown', escapeHandler);
            }
        };
        document.addEventListener('keydown', escapeHandler);

        // Show dialog
        overlay.classList.add('pw-show');

        if (type === 'prompt') {
            setTimeout(() => inputEl.focus(), 50);
        } else {
            setTimeout(() => newConfirmBtn.focus(), 50);
        }
    });
}

/** Closes the active dialog */
function closeDialog(result) {
    const overlay = document.getElementById('pw-dialog-overlay');
    if (overlay) {
        overlay.classList.add('pw-closing');

        const dialogBox = overlay.querySelector('.pw-dialog-box');

        const cleanup = () => {
            overlay.classList.remove('pw-show', 'pw-closing');
            if (dialogBox) {
                dialogBox.removeEventListener('animationend', cleanup);
            }
        };

        if (dialogBox) {
            dialogBox.addEventListener('animationend', cleanup);
            // Fallback in case animation fails
            setTimeout(cleanup, 250);
        } else {
            cleanup();
        }
    }

    if (currentDialogResolve) {
        currentDialogResolve(result);
        currentDialogResolve = null;
    }
}

/** Initializes treeview interaction */
function initTreeview() {
    const toggles = document.querySelectorAll('.pw-tree-toggle');

    toggles.forEach(toggle => {
        toggle.addEventListener('click', (e) => {
            // Prevent the click from selecting the item
            e.stopPropagation();

            const node = toggle.closest('.pw-tree-node');
            if (node && node.classList.contains('pw-has-children')) {
                node.classList.toggle('pw-expanded');
            }
        });
    });
}

/** Initializes treeview search */
function initTreeSearch() {
    const input = document.getElementById('pw-tree-search-input');
    if (!input) return;

    const treeview = document.querySelector('.pw-treeview');
    if (!treeview) return;

    let debounceTimer = null;
    let savedExpandState = null;

    input.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => filterTree(input.value.trim()), 200);
    });

    /**
     * Filters treeview nodes based on given string
     * @param {string} query - The search term to filter
     */
    function filterTree(query) {
        const allNodes = treeview.querySelectorAll('.pw-tree-node');

        if (!query) {
            allNodes.forEach(node => node.classList.remove('pw-tree-hidden'));
            restoreExpandState();
            savedExpandState = null;
            return;
        }

        if (!savedExpandState) {
            saveExpandState();
        }

        const lowerQuery = query.toLowerCase();

        allNodes.forEach(node => node.classList.add('pw-tree-hidden'));

        allNodes.forEach(node => {
            const label = node.querySelector(':scope > .pw-tree-item .pw-tree-label');
            if (!label) return;

            if (label.textContent.toLowerCase().includes(lowerQuery)) {
                node.classList.remove('pw-tree-hidden');
                node.classList.add('pw-expanded');

                let parent = node.parentElement.closest('.pw-tree-node');
                while (parent) {
                    parent.classList.remove('pw-tree-hidden');
                    parent.classList.add('pw-expanded');
                    parent = parent.parentElement.closest('.pw-tree-node');
                }
            }
        });
    }

    /** Saves the current expand/collapse state of all tree nodes */
    function saveExpandState() {
        savedExpandState = new Map();
        treeview.querySelectorAll('.pw-tree-node').forEach(node => {
            savedExpandState.set(node, node.classList.contains('pw-expanded'));
        });
    }

    /** Restores the previously saved expand/collapse state of all tree nodes */
    function restoreExpandState() {
        if (!savedExpandState) return;
        savedExpandState.forEach((wasExpanded, node) => {
            node.classList.toggle('pw-expanded', wasExpanded);
        });
    }
}

let currentSelectedPath = null;
let currentSelectedName = null;

/** Initializes dashboard main interactions */
function initDashboardInteractions() {
    const treeItems = document.querySelectorAll('.pw-tree-item');
    const mainActions = document.getElementById('pw-main-actions');
    const mainContent = document.getElementById('pw-main-content');

    const btnAdd = document.getElementById('pw-btn-add-subpage');
    const btnDelete = document.getElementById('pw-btn-delete-page');
    const btnEdit = document.getElementById('pw-btn-edit-page');
    const btnSettings = document.getElementById('pw-btn-page-settings-dash');
    const btnDuplicate = document.getElementById('pw-btn-duplicate-page');

    if (btnEdit) {
        btnEdit.addEventListener('click', () => {
            if (!currentSelectedPath) return;
            if (typeof openEditorForPage === 'function') {
                openEditorForPage(currentSelectedPath, currentSelectedName);
            } else {
                window.location.href = (window.PW_BASE_PATH || '') + '/dashboard/edit?path=' + encodeURIComponent(currentSelectedPath);
            }
        });
    }

    if (btnSettings) {
        btnSettings.addEventListener('click', () => {
            if (!currentSelectedPath) return;
            if (typeof openEditorForPage === 'function') {
                openEditorForPage(currentSelectedPath, currentSelectedName, true);
            } else {
                window.location.href = (window.PW_BASE_PATH || '') + '/dashboard/page-settings?path=' + encodeURIComponent(currentSelectedPath) + '&from=dashboard';
            }
        });
    }

    bindSidebarTreeItems(treeItems, mainActions, mainContent, btnAdd, btnDelete, btnDuplicate, btnSettings);
    bindAddSubPageAction(btnAdd);
    bindDuplicatePageAction(btnDuplicate);
    bindDeletePageAction(btnDelete);
    restoreActivePageState();
}

/** Initializes snippet create button logic */
function initSnippets() {
    const btnAddSnippet = document.getElementById('pw-btn-add-snippet');

    if (btnAddSnippet) {
        btnAddSnippet.addEventListener('click', async () => {
            const newSnippetData = await openDialog({
                title: __('dashboard.add_snippet') || 'Add Snippet',
                text: __('dashboard.enter_snippet_title') || 'Enter a title for the new snippet:',
                type: 'prompt',
                placeholder: 'My Snippet'
            });

            if (newSnippetData && newSnippetData.value && newSnippetData.value.trim() !== '') {
                const title = newSnippetData.value.trim();
                const result = await apiSafe('create_snippet', { title });
                if (result) {
                    sessionStorage.setItem('pw-notify', JSON.stringify({text: __('dashboard.snippet_created'), type: 'success'}));
                    window.location.href = (window.PW_BASE_PATH || '') + '/dashboard/edit?path=' + encodeURIComponent(result.new_path);
                } else {
                    notify(__('dashboard.error_create_snippet'), 'error');
                }
            }
        });
    }
}

/** Sidebar Click Logic */
function bindSidebarTreeItems(treeItems, mainActions, mainContent, btnAdd, btnDelete, btnDuplicate, btnSettings) {
    treeItems.forEach(item => {
        item.addEventListener('click', () => {
            treeItems.forEach(el => el.classList.remove('pw-tree-active'));

            item.classList.add('pw-tree-active');

            const path = item.getAttribute('data-path');
            const label = item.querySelector('.pw-tree-label').textContent;

            currentSelectedPath = path;
            currentSelectedName = label;
            sessionStorage.setItem('pw_active_page', path);

            if (mainActions) mainActions.style.display = 'flex';

            const isSystemPage = path.startsWith('/_virtual/');
            const isSnippet = path.startsWith('/_snippets/');

            // Configure primary button visibility based on system page rules
            if (path === '/') {
                if (btnDelete) btnDelete.style.display = 'none';
                if (btnDuplicate) btnDuplicate.style.display = 'none';
                if (btnAdd) btnAdd.style.display = 'inline-flex';
                if (btnSettings) btnSettings.style.display = 'inline-flex';
            } else if (isSystemPage) {
                if (btnDelete) btnDelete.style.display = 'none';
                if (btnDuplicate) btnDuplicate.style.display = 'none';
                if (btnAdd) btnAdd.style.display = 'none';
                if (btnSettings) btnSettings.style.display = 'none';
            } else if (isSnippet) {
                if (btnDelete) btnDelete.style.display = 'inline-flex';
                if (btnDuplicate) btnDuplicate.style.display = 'none';
                if (btnAdd) btnAdd.style.display = 'none';
                if (btnSettings) btnSettings.style.display = 'none';
            } else {
                if (btnDelete) btnDelete.style.display = 'inline-flex';
                if (btnDuplicate) btnDuplicate.style.display = 'inline-flex';
                if (btnAdd) btnAdd.style.display = 'inline-flex';
                if (btnSettings) btnSettings.style.display = 'inline-flex';
            }

            // Update action button texts if snippet is selected
            const btnEdit = document.getElementById('pw-btn-edit-page');
            if (btnEdit) {
                btnEdit.innerHTML = isSnippet
                    ? '<iconify-icon icon="mdi:pencil"></iconify-icon> ' + (__('dashboard.edit_snippet') || 'Edit Snippet')
                    : '<iconify-icon icon="mdi:pencil"></iconify-icon> ' + (__('dashboard.edit_page') || 'Edit Page');
            }

            if (btnDelete) {
                btnDelete.innerHTML = isSnippet
                    ? '<iconify-icon icon="mdi:delete"></iconify-icon> ' + (__('dashboard.delete_snippet') || 'Delete Snippet')
                    : '<iconify-icon icon="mdi:delete"></iconify-icon> ' + (__('dashboard.delete_page') || 'Delete Page');
            }

            resetDeleteButton(btnDelete, isSnippet);

            if (mainContent) {
                const headerTpl = document.getElementById('tpl-page-header');
                mainContent.innerHTML = '';
                if (headerTpl) {
                    const header = headerTpl.content.cloneNode(true);
                    header.querySelector('[data-field="label"]').textContent = label;
                    header.querySelector('[data-field="path"]').textContent = path;
                    mainContent.appendChild(header);
                }
                const loadingP = document.createElement('p');
                loadingP.textContent = __('common.loading_details');
                mainContent.appendChild(loadingP);
                apiCall('get_page', { path })
                .then(result => {
                    if (loadingP.parentNode) loadingP.remove();

                    if (result.success && result.data) {
                        const d = result.data;
                        const created = d.DateCreated ? new Date(d.DateCreated).toLocaleString() : __('common.na');
                        const modified = d.DateModified ? new Date(d.DateModified).toLocaleString() : __('common.na');
                        const desc = d.description || d.Description || __('common.no_description');

                        const infoTpl = document.getElementById('tpl-page-info');
                        if (infoTpl) {
                            const info = infoTpl.content.cloneNode(true);
                            info.querySelector('[data-field="author"]').textContent = d.Author || '—';
                            info.querySelector('[data-field="created"]').textContent = created;
                            info.querySelector('[data-field="modified"]').textContent = modified;
                            info.querySelector('[data-field="description"]').textContent = desc;
                            info.querySelector('[data-field="tags"]').textContent = (Array.isArray(d.Tags) && d.Tags.length > 0) ? d.Tags.join(', ') : '—';
                            const translations = (Array.isArray(result.available_langs) ? result.available_langs : []).filter(l => l !== '');
                            info.querySelector('[data-field="translations"]').textContent = translations.length > 0 ? translations.join(', ') : '—';
                            if (result.is_draft) {
                                info.querySelector('[data-field="draft-badge"]').style.display = 'inline-block';
                            }
                            if (d.isPrivate) {
                                info.querySelector('[data-field="private-badge"]').style.display = 'inline-block';
                            }
                            mainContent.appendChild(info);
                        }

                        // Rename card
                        if (path !== '/' && !isSystemPage && !isSnippet) {
                            const renameTpl = document.getElementById('tpl-rename-card');
                            if (renameTpl) {
                                const pathParts = path.split('/').filter(Boolean);
                                const currentName = pathParts.pop();
                                const parentPath = pathParts.length > 0 ? '/' + pathParts.join('/') + '/' : '/';

                                const rename = renameTpl.content.cloneNode(true);
                                rename.querySelector('[data-field="parent-path"]').textContent = parentPath;
                                rename.querySelector('[data-field="current-name"]').value = currentName;
                                mainContent.appendChild(rename);
                            }
                        }

                        // Redirect card
                        if (path !== '/' && !isSystemPage && !isSnippet) {
                            const redirectTpl = document.getElementById('tpl-redirect-card');
                            if (redirectTpl) {
                                const redirect = redirectTpl.content.cloneNode(true);
                                if (d.Settings?.enable_redirect) {
                                    redirect.querySelector('#pw-check-enable-redirect').checked = true;
                                }
                                if (d.Settings?.redirect_url) {
                                    redirect.querySelector('#pw-input-redirect-url').value = d.Settings.redirect_url;
                                }
                                mainContent.appendChild(redirect);

                                // Attach PagePicker autocomplete to redirect URL input
                                const redirectInput = document.getElementById('pw-input-redirect-url');
                                if (redirectInput && typeof PagePicker !== 'undefined') {
                                    new PagePicker(redirectInput);
                                }
                            }
                        }

                        // Move card
                        if (path !== '/' && !isSystemPage && !isSnippet) {
                            const moveTpl = document.getElementById('tpl-move-card');
                            if (moveTpl) {
                                mainContent.appendChild(moveTpl.content.cloneNode(true));
                            }
                        }

                        // Comments sectiom for dashboard
                        if (!isSystemPage && !isSnippet) {
                            const commentsTpl = document.getElementById('tpl-comments-card');
                            if (commentsTpl) {
                                const card = commentsTpl.content.cloneNode(true);
                                const listContainer = card.querySelector('[data-field="comments-list"]');
                                const noCommentsMsg = card.querySelector('[data-field="no-comments"]');
                                const badge = card.querySelector('[data-field="pending-count"]');

                                mainContent.appendChild(card);

                                // Get comments from API
                                apiCall('list_comments', { path })
                                .then(res => {
                                    if (res && res.success && Array.isArray(res.data)) {
                                        const comments = res.data;
                                        if (comments.length === 0) {
                                            noCommentsMsg.style.display = 'block';
                                            return;
                                        }

                                        let pendingCount = 0;

                                        comments.forEach(c => {
                                            if (c.status === 'pending') pendingCount++;

                                            const item = document.createElement('div');
                                            item.className = 'pw-comment-admin-item';
                                            item.style.borderBottom = '1px solid var(--pw-border)';
                                            item.style.padding = '10px 0';

                                            const header = document.createElement('div');
                                            header.style.display = 'flex';
                                            header.style.justifyContent = 'space-between';
                                            header.style.fontSize = '0.9rem';

                                            const authorInfo = document.createElement('span');
                                            authorInfo.innerHTML = `<strong>${escapeHtml(c.name)}</strong> <a href="mailto:${escapeHtml(c.email)}" class="pw-muted" style="margin-left:8px;font-size:0.8rem;">&lt;${escapeHtml(c.email)}&gt;</a>`;

                                            const dateInfo = document.createElement('span');
                                            dateInfo.className = 'pw-text-muted';
                                            dateInfo.textContent = new Date(c.date).toLocaleString();

                                            header.appendChild(authorInfo);
                                            header.appendChild(dateInfo);
                                            item.appendChild(header);

                                            const body = document.createElement('div');
                                            body.style.margin = '5px 0';
                                            body.style.whiteSpace = 'pre-wrap';
                                            body.textContent = c.text;
                                            item.appendChild(body);

                                            const footer = document.createElement('div');
                                            footer.style.display = 'flex';
                                            footer.style.gap = '10px';
                                            footer.style.alignItems = 'center';
                                            footer.style.marginTop = '8px';

                                            // Status Badge
                                            const statusBadge = document.createElement('span');
                                            statusBadge.className = 'pw-badge';
                                            if (c.status === 'approved') {
                                                statusBadge.textContent = __('comments.status_approved') || 'Approved';
                                                statusBadge.style.backgroundColor = 'rgba(40, 167, 69, 0.2)';
                                                statusBadge.style.color = '#28a745';
                                            } else if (c.status === 'hidden') {
                                                statusBadge.textContent = __('comments.status_hidden') || 'Hidden';
                                                statusBadge.style.backgroundColor = 'rgba(108, 117, 125, 0.2)';
                                                statusBadge.style.color = '#6c757d';
                                            } else {
                                                statusBadge.textContent = __('comments.status_pending') || 'Pending';
                                                statusBadge.style.backgroundColor = 'rgba(255, 193, 7, 0.2)';
                                                statusBadge.style.color = '#ffc107';
                                            }
                                            footer.appendChild(statusBadge);

                                            if (c.spam) {
                                                const spamBadge = document.createElement('span');
                                                spamBadge.className = 'pw-badge';
                                                spamBadge.textContent = __('comments.spam') || 'Spam';
                                                spamBadge.style.backgroundColor = 'rgba(220, 53, 69, 0.2)';
                                                spamBadge.style.color = '#dc3545';
                                                spamBadge.style.borderColor = 'rgba(220, 53, 69, 0.3)';
                                                spamBadge.style.marginLeft = '5px';
                                                footer.appendChild(spamBadge);
                                            }

                                            // Action Buttons
                                            if (c.status === 'pending' || c.status === 'hidden') {
                                                const btnApprove = document.createElement('button');
                                                btnApprove.className = 'pw-btn pw-btn-primary';
                                                btnApprove.style.padding = '2px 8px';
                                                btnApprove.style.fontSize = '0.8rem';
                                                btnApprove.textContent = __('comments.approve') || 'Approve';
                                                btnApprove.onclick = async () => {
                                                    const ok = await apiSafe('moderate_comment', { path, comment_id: c.id, mod_action: 'approve' });
                                                    if (ok) {
                                                        const activeItem = document.querySelector('.pw-tree-item.pw-tree-active');
                                                        if (activeItem) activeItem.click();
                                                    }
                                                };
                                                footer.appendChild(btnApprove);
                                            }

                                            if (c.status === 'approved') {
                                                const btnHide = document.createElement('button');
                                                btnHide.className = 'pw-btn';
                                                btnHide.style.padding = '2px 8px';
                                                btnHide.style.fontSize = '0.8rem';
                                                btnHide.textContent = __('comments.hide') || 'Hide';
                                                btnHide.onclick = async () => {
                                                    const ok = await apiSafe('moderate_comment', { path, comment_id: c.id, mod_action: 'hide' });
                                                    if (ok) {
                                                        const activeItem = document.querySelector('.pw-tree-item.pw-tree-active');
                                                        if (activeItem) activeItem.click();
                                                    }
                                                };
                                                footer.appendChild(btnHide);
                                            }

                                            const btnDelete = document.createElement('button');
                                            btnDelete.className = 'pw-btn pw-btn-danger';
                                            btnDelete.style.padding = '2px 8px';
                                            btnDelete.style.fontSize = '0.8rem';
                                            btnDelete.textContent = __('common.delete') || 'Delete';
                                            btnDelete.onclick = async () => {
                                                const confirmed = await openDialog({
                                                    title: __('comments.delete') || 'Delete Comment',
                                                    text: __('comments.delete_confirm') || 'Are you sure you want to delete this comment?',
                                                    confirmText: __('common.delete') || 'Delete',
                                                    cancelText: __('common.cancel') || 'Cancel',
                                                    type: 'confirm'
                                                });
                                                if (confirmed) {
                                                    const ok = await apiSafe('moderate_comment', { path, comment_id: c.id, mod_action: 'delete' });
                                                    if (ok) {
                                                        const activeItem = document.querySelector('.pw-tree-item.pw-tree-active');
                                                        if (activeItem) activeItem.click();
                                                    }
                                                }
                                            };
                                            footer.appendChild(btnDelete);

                                            item.appendChild(footer);
                                            listContainer.appendChild(item);
                                        });

                                        if (pendingCount > 0) {
                                            badge.style.display = 'inline-block';
                                            badge.textContent = pendingCount;
                                        }
                                    } else {
                                        noCommentsMsg.style.display = 'block';
                                    }
                                })
                                .catch(() => {
                                    noCommentsMsg.style.display = 'block';
                                });
                            }
                        }

                        // Recent pages section for dashboard startpage
                        if (path === '/') {
                            const recentTpl = document.getElementById('tpl-recent-pages-card');
                            if (recentTpl) {
                                const recentCard = recentTpl.content.cloneNode(true);
                                const listContainer = recentCard.querySelector('[data-field="recent-pages-list"]');
                                const noPagesMsg = recentCard.querySelector('[data-field="no-recent-pages"]');

                                mainContent.appendChild(recentCard);

                                apiSafe('list_recent_pages', { limit: 10 })
                                .then(res => {
                                    if (res && res.success && Array.isArray(res.data) && res.data.length > 0) {
                                        const pages = res.data;
                                        listContainer.innerHTML = '';
                                        pages.forEach(p => {
                                            const row = document.createElement('div');
                                            row.className = 'pw-recent-page-item';

                                            const left = document.createElement('div');
                                            left.style.minWidth = '0';

                                            let badgesHtml = '';
                                            if (p.is_draft) {
                                                badgesHtml += `<span class="pw-draft-badge" style="font-size:9px;margin-left:6px;">${__('dashboard.draft')}</span>`;
                                            }
                                            if (p.is_private) {
                                                badgesHtml += `<span class="pw-private-badge" style="font-size:9px;margin-left:6px;">${__('dashboard.private')}</span>`;
                                            }

                                            left.innerHTML = `
                                                <div><span class="pw-recent-page-title">${escapeHtml(p.title || p.path)}</span>${badgesHtml}</div>
                                                <div class="pw-recent-page-path">${escapeHtml(p.path)}</div>
                                            `;

                                            const right = document.createElement('div');
                                            right.className = 'pw-recent-page-meta';

                                            const dateStr = p.modified ? formatPwDate(new Date(p.modified)) : '—';
                                            const authorStr = p.author ? ` (${escapeHtml(p.author)})` : '';
                                            right.innerHTML = `<span>${escapeHtml(dateStr)}${authorStr}</span>`;

                                            const editBtn = document.createElement('button');
                                            editBtn.className = 'pw-btn pw-recent-page-action-btn';
                                            editBtn.title = __('dashboard.edit_page') || 'Edit Page';
                                            editBtn.innerHTML = '<iconify-icon icon="mdi:pencil"></iconify-icon>';
                                            editBtn.onclick = (e) => {
                                                e.stopPropagation();
                                                window.location.href = window.PW_BASE_PATH + '/dashboard/edit?path=' + encodeURIComponent(p.path);
                                            };
                                            right.appendChild(editBtn);

                                            if (p.is_published) {
                                                const viewBtn = document.createElement('button');
                                                viewBtn.className = 'pw-btn pw-recent-page-action-btn';
                                                viewBtn.title = __('dashboard.view_live_page') || 'Open Live Page';
                                                viewBtn.innerHTML = '<iconify-icon icon="mdi:open-in-new"></iconify-icon>';
                                                viewBtn.onclick = (e) => {
                                                    e.stopPropagation();
                                                    const liveUrl = (window.PW_BASE_PATH || '') + (p.path === '/' ? '/' : p.path);
                                                    window.open(liveUrl, '_blank');
                                                };
                                                right.appendChild(viewBtn);
                                            }

                                            row.appendChild(left);
                                            row.appendChild(right);

                                            row.onclick = () => {
                                                const treeItem = document.querySelector(`.pw-tree-item[data-path="${p.path}"]`);
                                                if (treeItem) {
                                                    treeItem.click();
                                                    treeItem.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                                                }
                                            };

                                            listContainer.appendChild(row);
                                        });
                                    } else {
                                        noPagesMsg.style.display = 'block';
                                    }
                                });
                            }
                        }
                    } else {
                        const fallback = document.createElement('p');
                        fallback.textContent = __('common.use_buttons');
                        mainContent.appendChild(fallback);
                    }

                    const btnMoveUp = document.getElementById('pw-btn-move-up');
                    const btnMoveDown = document.getElementById('pw-btn-move-down');

                    const handleMove = async (dir) => {
                        const result = await apiSafe('move_page', { path, direction: dir });
                        if (result) {
                            sessionStorage.setItem('pw-notify', JSON.stringify({text: __('dashboard.page_moved'), type: 'success'}));
                            window.location.reload();
                        }
                    };

                    if (btnMoveUp) btnMoveUp.addEventListener('click', () => handleMove('up'));
                    if (btnMoveDown) btnMoveDown.addEventListener('click', () => handleMove('down'));

                    const btnRename = document.getElementById('pw-btn-rename-folder');
                    if (btnRename) {
                        btnRename.addEventListener('click', async () => {
                            const newName = document.getElementById('pw-input-rename-folder').value.trim();
                            if (!newName) return;

                            if (newName.startsWith('_')) {
                                notify(__('dashboard.error_no_underscore'), 'error');
                                return;
                            }

                            const result = await apiSafe('rename_folder', { path, new_name: newName });
                            if (result) {
                                sessionStorage.setItem('pw_active_page', result.new_path);
                                sessionStorage.setItem('pw-notify', JSON.stringify({text: __('dashboard.folder_renamed'), type: 'success'}));
                                window.location.reload();
                            }
                        });
                    }

                    const btnSaveRedirect = document.getElementById('pw-btn-save-redirect');
                    if (btnSaveRedirect) {
                        btnSaveRedirect.addEventListener('click', async () => {
                            const isEnabled = document.getElementById('pw-check-enable-redirect').checked;
                            const url = document.getElementById('pw-input-redirect-url').value.trim();

                            const result = await apiSafe('save_page_settings', {
                                path,
                                enable_redirect: isEnabled,
                                redirect_url: url
                            });
                            if (result) {
                                notify(__('dashboard.redirect_saved'), 'success');
                            }
                        });
                    }
                })
                .catch(err => {
                    if (loadingP.parentNode) loadingP.remove();
                    const errP = document.createElement('p');
                    errP.textContent = __('common.failed_load_details');
                    mainContent.appendChild(errP);
                    console.error(err);
                });
            }

            // Reset delete button
            resetDeleteButton(btnDelete, isSnippet);
        });
    });
}

/** Add Sub-Page Logic */
function bindAddSubPageAction(btnAdd) {
    if (!btnAdd) return;

    btnAdd.addEventListener('click', async () => {
        if (!currentSelectedPath) return;

        const newPageData = await openDialog({
            title: __('dashboard.create_subpage'),
            text: __('dashboard.enter_title'),
            type: 'prompt',
            placeholder: __('dashboard.enter_title_placeholder'),
            showLayout: true
        });

        if (newPageData && newPageData.value && newPageData.value.trim() !== '') {
            const title = newPageData.value.trim();
            const layout = newPageData.layout || 'page';
            const parentPath = currentSelectedPath;

            const result = await apiSafe('create_page', { title, parent_path: parentPath, layout });
            if (result) {
                sessionStorage.setItem('pw-notify', JSON.stringify({text: __('dashboard.page_created'), type: 'success'}));
                window.location.href = (window.PW_BASE_PATH || '') + '/dashboard/edit?path=' + encodeURIComponent(result.new_path);
            }
        }
    });
}

/** Duplicate Page Logic */
function bindDuplicatePageAction(btnDuplicate) {
    if (!btnDuplicate) return;

    btnDuplicate.addEventListener('click', async () => {
        if (!currentSelectedPath) return;

        const newPageData = await openDialog({
            title: __('dashboard.duplicate_title'),
            text: __('dashboard.enter_duplicate_title', currentSelectedName),
            type: 'prompt',
            placeholder: currentSelectedName + ' Copy'
        });

        if (newPageData && newPageData.value && newPageData.value.trim() !== '') {
            const result = await apiSafe('duplicate_page', {
                path: currentSelectedPath,
                title: newPageData.value.trim()
            });
            if (result) {
                if (result.new_path) {
                    sessionStorage.setItem('pw_active_page', result.new_path);
                }
                sessionStorage.setItem('pw-notify', JSON.stringify({text: __('dashboard.page_duplicated'), type: 'success'}));
                window.location.reload();
            } else {
                notify(__('dashboard.error_duplicate_page'), 'error');
            }
        }
    });
}

/** Delete Page Logic */
function bindDeletePageAction(btnDelete) {
    if (!btnDelete) return;

    let isDeleteConfirm = false;

    const handleDeleteClick = async (e) => {
        e.stopPropagation(); // prevent from resetting immediately

        if (!currentSelectedPath) return;

        if (currentSelectedPath === '/') {
            await openDialog({ title: __('dashboard.action_denied'), text: __('dashboard.cannot_delete_root'), type: 'alert' });
            return;
        }

        if (!isDeleteConfirm) {
            isDeleteConfirm = true;
            btnDelete.textContent = __('common.confirm');
            btnDelete.classList.add('pw-confirm-state');
            btnDelete.style.backgroundColor = 'darkred';
        } else {
            const endpoint = currentSelectedPath.startsWith('/_snippets/') ? 'delete_snippet' : 'delete_page';
            const result = await apiSafe(endpoint, { path: currentSelectedPath });
            if (result) {
                const isSnippet = currentSelectedPath && currentSelectedPath.startsWith('/_snippets/');
                sessionStorage.setItem('pw-notify', JSON.stringify({text: isSnippet ? __('dashboard.snippet_deleted') : __('dashboard.page_deleted'), type: 'success'}));
                window.location.reload();
            } else {
                const isSnippet = currentSelectedPath && currentSelectedPath.startsWith('/_snippets/');
                notify(isSnippet ? __('dashboard.error_delete_snippet') : __('dashboard.error_delete_page'), 'error');
                resetDeleteButton(btnDelete, isSnippet);
                isDeleteConfirm = false;
            }
        }
    };

    btnDelete.addEventListener('click', handleDeleteClick);

    // Click anywhere resets delete button
    window.addEventListener('click', (e) => {
        if (isDeleteConfirm && e.target !== btnDelete && !btnDelete.contains(e.target)) {
            const isSnippet = currentSelectedPath && currentSelectedPath.startsWith('/_snippets/');
            resetDeleteButton(btnDelete, isSnippet);
            isDeleteConfirm = false;
        }
    });
}

/** Restores active page state from sessionStorage on load */
function restoreActivePageState() {
    const savedPath = sessionStorage.getItem('pw_active_page');
    if (savedPath) {
        const targetItem = document.querySelector(`.pw-tree-item[data-path="${savedPath}"]`);
        if (targetItem) {
            // Expand all parent nodes
            let parentNode = targetItem.closest('.pw-tree-node');
            while (parentNode) {
                parentNode.classList.add('pw-expanded');
                parentNode = parentNode.parentElement.closest('.pw-tree-node');
            }
            // Trigger click to load content
            targetItem.click();
        }
    }
}

/** Resets the delete button */
function resetDeleteButton(btn, isSnippet = false) {
    if (!btn) return;
    btn.innerHTML = isSnippet
        ? '<iconify-icon icon="mdi:delete"></iconify-icon> ' + (__('dashboard.delete_snippet') || 'Delete Snippet')
        : '<iconify-icon icon="mdi:delete"></iconify-icon> ' + (__('dashboard.delete_page') || 'Delete Page');
    btn.classList.remove('pw-confirm-state');
    btn.style.backgroundColor = '';
}

/** Initializes Drag and Drop functionality for treeview. */
function initTreeDragAndDrop() {
    const items = document.querySelectorAll('.pw-tree-item');
    let draggedPath = null;

    items.forEach(item => {
        // Prevent draggability for root and virtual system pages
        if (item.dataset.path !== '/' && !item.dataset.path.startsWith('/_')) {
            item.addEventListener('dragstart', (e) => {
                draggedPath = item.dataset.path;
                e.dataTransfer.effectAllowed = 'move';
                setTimeout(() => item.style.opacity = '0.5', 10);
            });

            item.addEventListener('dragend', () => {
                draggedPath = null;
                item.style.opacity = '1';
                clearDragClasses();
            });
        }

        item.addEventListener('dragover', (e) => {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';

            const targetPath = item.dataset.path;

            // Prevent dropping around virtual system pages
            if (targetPath && targetPath.startsWith('/_')) return;
            if (!draggedPath || draggedPath === targetPath) {
                clearDragClasses();
                return;
            }

            if (targetPath.startsWith(draggedPath + '/')) {
                clearDragClasses();
                return;
            }

            clearDragClasses();

            const rect = item.getBoundingClientRect();
            const y = e.clientY - rect.top;

            const threshold = rect.height * 0.35;

            if (targetPath === '/') {
                item.classList.add('pw-drop-inside');
                item.dataset.dropAction = 'inside';
            } else {
                if (y < threshold) {
                    item.classList.add('pw-drop-before');
                    item.dataset.dropAction = 'before';
                } else if (y > rect.height - threshold) {
                    item.classList.add('pw-drop-after');
                    item.dataset.dropAction = 'after';
                } else {
                    item.classList.add('pw-drop-inside');
                    item.dataset.dropAction = 'inside';
                }
            }
        });

        item.addEventListener('dragleave', (e) => {
            clearDragClasses();
        });

        item.addEventListener('drop', async (e) => {
            e.preventDefault();
            const dropAction = item.dataset.dropAction;
            const targetPath = item.dataset.path;

            clearDragClasses();

            if (!draggedPath || !dropAction || draggedPath === targetPath) {
                return;
            }

            if (targetPath.startsWith(draggedPath + '/')) {
                notify('Cannot move a folder into its own subfolder.', 'error');
                return;
            }

            const result = await apiSafe('drag_drop_page', {
                source_path: draggedPath,
                target_path: targetPath,
                position: dropAction
            });
            if (result) {
                sessionStorage.setItem('pw-notify', JSON.stringify({text: __('dashboard.page_moved'), type: 'success'}));
                window.location.reload();
            } else {
                notify(__('dashboard.error_move_page'), 'error');
            }
        });
    });

    function clearDragClasses() {
        items.forEach(el => {
            el.classList.remove('pw-drop-before', 'pw-drop-after', 'pw-drop-inside');
            delete el.dataset.dropAction;
        });
    }
}

/**
 * Initialize responsive sidebar toggle
 */
function initAdminSidebarToggle(btnId) {
    const btn = document.getElementById(btnId);
    const sidebar = document.querySelector('.pw-dashboard-sidebar');
    if (!btn || !sidebar) return;

    // backdrop
    let backdrop = document.getElementById('pw-sidebar-backdrop');
    if (!backdrop) {
        backdrop = document.createElement('div');
        backdrop.id = 'pw-sidebar-backdrop';
        backdrop.className = 'pw-sidebar-backdrop';
        document.body.appendChild(backdrop);
    }

    const toggleSidebar = (show) => {
        const shouldShow = show !== undefined ? show : !sidebar.classList.contains('pw-show');
        sidebar.classList.toggle('pw-show', shouldShow);
        backdrop.classList.toggle('pw-show', shouldShow);
    };

    btn.addEventListener('click', (e) => {
        e.stopPropagation();
        toggleSidebar();
    });

    backdrop.addEventListener('click', () => {
        toggleSidebar(false);
    });

    // Close when clicking outside of the sidebar
    document.addEventListener('click', (e) => {
        if (window.innerWidth <= 800) {
            if (sidebar.classList.contains('pw-show') && !sidebar.contains(e.target) && e.target !== btn) {
                toggleSidebar(false);
            }
        }
    });

    // Close after selecting a page or tab on mobile
    const treeLinks = sidebar.querySelectorAll('.pw-tree-item');
    treeLinks.forEach(link => {
        link.addEventListener('click', () => {
            if (window.innerWidth <= 800) {
                toggleSidebar(false);
            }
        });
    });
}

/**
 * Escapes HTML characters
 * @param {string} text - text to escape
 * @returns {string} Escaped text
 */
function escapeHtml(text) {
    if (typeof text !== 'string') return '';
    return text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
