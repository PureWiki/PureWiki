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

/** header user profile menu dropdown */
function initUserMenu() {
    const wrapper = document.getElementById('pw-user-menu-wrapper');
    const toggleBtn = document.getElementById('pw-user-menu-toggle');
    const dropdown = document.getElementById('pw-user-dropdown');
    const btnToggleTheme = document.getElementById('pw-btn-toggle-theme');
    const btnChangeLang = document.getElementById('pw-btn-change-language');
    const btnChangePw = document.getElementById('pw-btn-change-password');

    if (!wrapper || !toggleBtn || !dropdown) return;

    const toggleMenu = (show) => {
        const shouldShow = show !== undefined ? show : !dropdown.classList.contains('pw-show');
        dropdown.classList.toggle('pw-show', shouldShow);
    };

    toggleBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        toggleMenu();
    });

    document.addEventListener('click', (e) => {
        if (!wrapper.contains(e.target)) {
            toggleMenu(false);
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && dropdown.classList.contains('pw-show')) {
            toggleMenu(false);
        }
    });

    if (btnToggleTheme) {
        btnToggleTheme.addEventListener('click', async (e) => {
            e.stopPropagation();
            const currentTheme = document.documentElement.getAttribute('data-theme') === 'light' ? 'light' : 'dark';
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';

            if (newTheme === 'light') {
                document.documentElement.setAttribute('data-theme', 'light');
            } else {
                document.documentElement.removeAttribute('data-theme');
            }

            const icon = btnToggleTheme.querySelector('iconify-icon');
            const label = document.getElementById('pw-user-theme-label');
            if (icon) {
                icon.setAttribute('icon', newTheme === 'light' ? 'mdi:weather-night' : 'mdi:weather-sunny');
            }
            if (label) {
                label.textContent = newTheme === 'light' ? __('auth.theme_dark') : __('auth.theme_light');
            }
            btnToggleTheme.setAttribute('data-theme', newTheme);

            await apiSafe('change_theme', { theme: newTheme });
        });
    }

    if (btnChangeLang) {
        btnChangeLang.addEventListener('click', (e) => {
            e.stopPropagation();
            toggleMenu(false);
            openChangeLanguageDialog();
        });
    }

    if (btnChangePw) {
        btnChangePw.addEventListener('click', (e) => {
            e.stopPropagation();
            toggleMenu(false);
            openChangePasswordDialog();
        });
    }
}

/** Opens dialog to change dashboard language for the current user */
async function openChangeLanguageDialog() {
    if (!document.getElementById('pw-dialog-overlay')) {
        initDialogSystem();
    }

    const available = window.PW_AVAILABLE_LANGS || {};
    const current = window.PW_CURRENT_LANG || 'en';

    let optionsHtml = '';
    for (const [code, name] of Object.entries(available)) {
        const isSelected = code === current ? 'selected' : '';
        optionsHtml += `<option value="${escapeHtml(code)}" ${isSelected}>${escapeHtml(name)}</option>`;
    }

    const html = `
        <div style="display: flex; flex-direction: column; gap: 8px; margin-top: 5px;">
            <label for="pw-user-lang-select" style="display: block; font-size: 13px; color: var(--pw-text-muted);">${__('auth.select_language')}</label>
            <select id="pw-user-lang-select" class="pw-input pw-w-full">
                ${optionsHtml}
            </select>
        </div>
    `;

    const confirmed = await openDialog({
        title: __('auth.change_language'),
        html: html,
        type: 'confirm',
        confirmText: __('common.save'),
        cancelText: __('common.cancel')
    });

    if (!confirmed) return;

    const selectEl = document.getElementById('pw-user-lang-select');
    const selectedLang = selectEl ? selectEl.value : '';
    if (!selectedLang || selectedLang === current) return;

    const res = await apiSafe('change_language', {
        language: selectedLang
    });

    if (res && res.success !== false) {
        notify(res.message || __('auth.language_changed'), 'success');
        setTimeout(() => {
            window.location.reload();
        }, 600);
    }
}

/** Opens dialog to change the current user password */
async function openChangePasswordDialog() {
    if (!document.getElementById('pw-dialog-overlay')) {
        initDialogSystem();
    }

    const html = `
        <div style="display: flex; flex-direction: column; gap: 12px; margin-top: 5px;">
            <div>
                <label style="display: block; font-size: 13px; margin-bottom: 4px; color: var(--pw-text-muted);">${__('auth.current_password')}</label>
                <input id="pw-cpw-current" type="password" class="pw-input" autocomplete="current-password" required>
            </div>
            <div>
                <label style="display: block; font-size: 13px; margin-bottom: 4px; color: var(--pw-text-muted);">${__('auth.new_password')}</label>
                <input id="pw-cpw-new" type="password" class="pw-input" autocomplete="new-password" placeholder="${__('auth.min_chars_hint') || 'Mind. 8 Zeichen'}" required>
                <div id="pw-cpw-len-hint" style="font-size: 11px; margin-top: 4px; min-height: 14px;"></div>
            </div>
            <div>
                <label style="display: block; font-size: 13px; margin-bottom: 4px; color: var(--pw-text-muted);">${__('auth.confirm_password')}</label>
                <input id="pw-cpw-confirm" type="password" class="pw-input" autocomplete="new-password" required>
                <div id="pw-cpw-match-hint" style="font-size: 11px; margin-top: 4px; min-height: 14px;"></div>
            </div>
        </div>
    `;

    const dialogPromise = openDialog({
        title: __('auth.change_password'),
        html: html,
        type: 'confirm',
        confirmText: __('common.save'),
        cancelText: __('common.cancel')
    });

    const currentInput = document.getElementById('pw-cpw-current');
    const newInput = document.getElementById('pw-cpw-new');
    const confirmInput = document.getElementById('pw-cpw-confirm');
    const lenHint = document.getElementById('pw-cpw-len-hint');
    const matchHint = document.getElementById('pw-cpw-match-hint');
    const confirmBtn = document.getElementById('pw-dialog-btn-confirm');

    function validateInputs() {
        const curVal = currentInput?.value || '';
        const newVal = newInput?.value || '';
        const confVal = confirmInput?.value || '';

        // Length validation
        if (newVal.length === 0) {
            lenHint.textContent = '';
        } else if (newVal.length < 8) {
            lenHint.textContent = `${newVal.length} / 8 ${__('auth.min_chars_hint')}`;
            lenHint.style.color = 'var(--pw-danger)';
        } else {
            lenHint.textContent = `✓ ${__('auth.min_chars_hint')}`;
            lenHint.style.color = 'var(--pw-success)';
        }

        // Match validation
        if (confVal.length === 0) {
            matchHint.textContent = '';
        } else if (newVal === confVal && newVal.length >= 8) {
            matchHint.textContent = `✓ ${__('auth.password_match') || 'Passwörter stimmen überein'}`;
            matchHint.style.color = 'var(--pw-success)';
        } else {
            matchHint.textContent = `✗ ${__('auth.error_password_mismatch') || 'Passwörter stimmen nicht überein'}`;
            matchHint.style.color = 'var(--pw-danger)';
        }

        const isValid = curVal.length > 0 && newVal.length >= 8 && newVal === confVal;
        if (confirmBtn) {
            confirmBtn.disabled = !isValid;
        }
    }

    if (confirmBtn) {
        confirmBtn.disabled = true;
    }

    [currentInput, newInput, confirmInput].forEach(input => {
        if (input) input.addEventListener('input', validateInputs);
    });

    const confirmed = await dialogPromise;
    if (!confirmed) return;

    const currentPassword = currentInput?.value || '';
    const newPassword = newInput?.value || '';

    const res = await apiSafe('change_password', {
        current_password: currentPassword,
        new_password: newPassword
    });

    if (res && res.success !== false) {
        notify(res.message || __('auth.password_changed'), 'success');
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
            ['pw-section-virtual-pages', 'pw-section-snippets'].forEach(secId => {
                const sec = document.getElementById(secId);
                if (sec) {
                    const key = secId === 'pw-section-virtual-pages' ? 'pw_sidebar_virtual_expanded' : 'pw_sidebar_snippets_expanded';
                    sec.classList.toggle('pw-expanded', localStorage.getItem(key) === 'true');
                }
            });
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

                const section = node.closest('.pw-sidebar-section');
                if (section) {
                    section.classList.add('pw-expanded');
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

/** Initializes collapsible sidebar sections */
function initCollapsibleSidebarSections() {
    const sections = [
        {
            id: 'pw-section-virtual-pages',
            headerId: 'pw-header-virtual-pages',
            storageKey: 'pw_sidebar_virtual_expanded'
        },
        {
            id: 'pw-section-snippets',
            headerId: 'pw-header-snippets',
            storageKey: 'pw_sidebar_snippets_expanded'
        }
    ];

    sections.forEach(({ id, headerId, storageKey }) => {
        const section = document.getElementById(id);
        const header = document.getElementById(headerId);
        if (!section || !header) return;

        // Load last state from localStorage
        const isExpanded = localStorage.getItem(storageKey) === 'true';
        section.classList.toggle('pw-expanded', isExpanded);

        const toggle = () => {
            const nowExpanded = section.classList.toggle('pw-expanded');
            try {
                localStorage.setItem(storageKey, nowExpanded ? 'true' : 'false');
            } catch (e) {}
        };

        header.addEventListener('click', (e) => {
            if (e.target.closest('#pw-btn-add-snippet')) return;
            toggle();
        });

        header.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                if (e.target.closest('#pw-btn-add-snippet')) return;
                e.preventDefault();
                toggle();
            }
        });
    });

    // Expand section if item inside is clicked
    const treeItems = document.querySelectorAll('.pw-sidebar-section .pw-tree-item');
    treeItems.forEach(item => {
        item.addEventListener('click', () => {
            const section = item.closest('.pw-sidebar-section');
            if (section && !section.classList.contains('pw-expanded')) {
                section.classList.add('pw-expanded');
                const key = section.id === 'pw-section-virtual-pages'
                    ? 'pw_sidebar_virtual_expanded'
                    : 'pw_sidebar_snippets_expanded';
                try {
                    localStorage.setItem(key, 'true');
                } catch (e) {}
            }
        });
    });
}

/** Initializes snippet create button logic */
function initSnippets() {
    const btnAddSnippet = document.getElementById('pw-btn-add-snippet');

    if (btnAddSnippet) {
        btnAddSnippet.addEventListener('click', async (e) => {
            e.stopPropagation();
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

                        const columnsContainer = document.createElement('div');
                        columnsContainer.className = 'pw-dashboard-columns';

                        const colLeft = document.createElement('div');
                        colLeft.className = 'pw-dashboard-column';

                        const colRight = document.createElement('div');
                        colRight.className = 'pw-dashboard-column';

                        columnsContainer.appendChild(colLeft);
                        columnsContainer.appendChild(colRight);
                        mainContent.appendChild(columnsContainer);

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
                            colLeft.appendChild(info);
                        }

                        // Recent pages section for dashboard startpage (right column)
                        if (path === '/') {
                            const recentTpl = document.getElementById('tpl-recent-pages-card');
                            if (recentTpl) {
                                const recentCard = recentTpl.content.cloneNode(true);
                                const listContainer = recentCard.querySelector('[data-field="recent-pages-list"]');
                                const noPagesMsg = recentCard.querySelector('[data-field="no-recent-pages"]');

                                colRight.appendChild(recentCard);

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

                        // Rename card (right column)
                        if (path !== '/' && !isSystemPage && !isSnippet) {
                            const renameTpl = document.getElementById('tpl-rename-card');
                            if (renameTpl) {
                                const pathParts = path.split('/').filter(Boolean);
                                const currentName = pathParts.pop();
                                const parentPath = pathParts.length > 0 ? '/' + pathParts.join('/') + '/' : '/';

                                const rename = renameTpl.content.cloneNode(true);
                                const parentSpan = rename.querySelector('[data-field="parent-path"]');
                                const renameInput = rename.querySelector('#pw-input-rename-folder');
                                const btnRename = rename.querySelector('#pw-btn-rename-folder');

                                if (parentSpan) parentSpan.textContent = parentPath;
                                if (renameInput) renameInput.value = currentName;
                                if (btnRename) btnRename.disabled = true;

                                const checkRenameChanged = () => {
                                    if (!renameInput || !btnRename) return;
                                    const val = renameInput.value.trim();
                                    btnRename.disabled = (val === '' || val === currentName);
                                };

                                if (renameInput) {
                                    renameInput.addEventListener('input', checkRenameChanged);
                                    renameInput.addEventListener('change', checkRenameChanged);
                                    renameInput.addEventListener('keydown', (e) => {
                                        if (e.key === 'Enter' && btnRename && !btnRename.disabled) {
                                            e.preventDefault();
                                            btnRename.click();
                                        }
                                    });
                                }

                                if (btnRename) {
                                    btnRename.addEventListener('click', async () => {
                                        if (btnRename.disabled) return;
                                        const newName = renameInput.value.trim();
                                        if (!newName || newName === currentName) {
                                            btnRename.disabled = true;
                                            return;
                                        }

                                        if (newName.startsWith('_')) {
                                            notify(__('dashboard.error_no_underscore'), 'error');
                                            return;
                                        }

                                        btnRename.disabled = true;
                                        const result = await apiSafe('rename_folder', { path, new_name: newName });
                                        if (result && result.new_path) {
                                            sessionStorage.setItem('pw_active_page', result.new_path);
                                            sessionStorage.setItem('pw-notify', JSON.stringify({ text: __('dashboard.folder_renamed'), type: 'success' }));
                                            window.location.reload();
                                        } else {
                                            btnRename.disabled = false;
                                        }
                                    });
                                }

                                colRight.appendChild(rename);
                            }
                        }

                        // Redirect card (right column)
                        if (path !== '/' && !isSystemPage && !isSnippet) {
                            const redirectTpl = document.getElementById('tpl-redirect-card');
                            if (redirectTpl) {
                                const redirect = redirectTpl.content.cloneNode(true);
                                const redirectInput = redirect.querySelector('#pw-input-redirect-url');
                                const btnToggleRedirect = redirect.querySelector('#pw-btn-toggle-redirect');
                                const badgeRedirectActive = redirect.querySelector('#pw-badge-redirect-active');

                                let savedUrl = (d.Settings?.redirect_url || '').trim();
                                let isRedirectActive = !!(d.Settings?.enable_redirect && savedUrl !== '');

                                const renderRedirectState = () => {
                                    if (isRedirectActive) {
                                        redirectInput.value = savedUrl;
                                        redirectInput.disabled = true;
                                        btnToggleRedirect.disabled = false;
                                        btnToggleRedirect.textContent = __('common.disable') || 'Disable';
                                        btnToggleRedirect.className = 'pw-btn pw-btn-danger';
                                        if (badgeRedirectActive) {
                                            badgeRedirectActive.textContent = __('common.active') || 'Aktiv';
                                            badgeRedirectActive.style.display = 'inline-block';
                                        }
                                    } else {
                                        redirectInput.value = savedUrl;
                                        redirectInput.disabled = false;
                                        btnToggleRedirect.disabled = (redirectInput.value.trim() === '');
                                        btnToggleRedirect.textContent = __('common.enable') || 'Enable';
                                        btnToggleRedirect.className = 'pw-btn pw-btn-primary';
                                        if (badgeRedirectActive) {
                                            badgeRedirectActive.style.display = 'none';
                                        }
                                    }
                                };

                                const onInputChange = () => {
                                    if (isRedirectActive) return;
                                    btnToggleRedirect.disabled = (redirectInput.value.trim() === '');
                                };

                                redirectInput.addEventListener('input', onInputChange);
                                redirectInput.addEventListener('change', onInputChange);

                                redirectInput.addEventListener('keydown', (e) => {
                                    if (e.key === 'Enter' && !btnToggleRedirect.disabled) {
                                        e.preventDefault();
                                        btnToggleRedirect.click();
                                    }
                                });

                                btnToggleRedirect.addEventListener('click', async () => {
                                    if (btnToggleRedirect.disabled) return;

                                    btnToggleRedirect.disabled = true;

                                    if (isRedirectActive) {
                                        // Disable redirect
                                        const result = await apiSafe('save_page_settings', {
                                            path,
                                            enable_redirect: false,
                                            redirect_url: savedUrl
                                        });

                                        if (result && result.success !== false) {
                                            notify(__('dashboard.redirect_saved'), 'success');
                                            isRedirectActive = false;
                                            renderRedirectState();
                                            redirectInput.focus();
                                        } else {
                                            btnToggleRedirect.disabled = false;
                                        }
                                    } else {
                                        // Enable redirect with the typed URL
                                        const url = redirectInput.value.trim();
                                        if (!url) {
                                            btnToggleRedirect.disabled = true;
                                            return;
                                        }

                                        const result = await apiSafe('save_page_settings', {
                                            path,
                                            enable_redirect: true,
                                            redirect_url: url
                                        });

                                        if (result && result.success !== false) {
                                            notify(__('dashboard.redirect_saved'), 'success');
                                            savedUrl = url;
                                            isRedirectActive = true;
                                            renderRedirectState();
                                        } else {
                                            btnToggleRedirect.disabled = false;
                                        }
                                    }
                                });

                                renderRedirectState();
                                colRight.appendChild(redirect);

                                // Attach PagePicker autocomplete to redirect URL input
                                const mountedInput = colRight.querySelector('#pw-input-redirect-url');
                                if (mountedInput && typeof PagePicker !== 'undefined') {
                                    new PagePicker(mountedInput);
                                }
                            }
                        }

                        // Move card (right column)
                        if (path !== '/' && !isSystemPage && !isSnippet) {
                            const moveTpl = document.getElementById('tpl-move-card');
                            if (moveTpl) {
                                colRight.appendChild(moveTpl.content.cloneNode(true));
                            }
                        }

                        // Backlinks card (right column)
                        if (!isSystemPage && !isSnippet) {
                            const backlinksTpl = document.getElementById('tpl-backlinks-card');
                            if (backlinksTpl) {
                                const card = backlinksTpl.content.cloneNode(true);
                                const cardContainer = card.firstElementChild;
                                const listContainer = card.querySelector('[data-field="backlinks-list"]');
                                const noBacklinksMsg = card.querySelector('[data-field="no-backlinks"]');
                                const toggleWrapper = card.querySelector('[data-field="backlinks-toggle-wrapper"]');
                                const toggleBtn = card.querySelector('[data-field="backlinks-toggle-btn"]');

                                colRight.appendChild(card);

                                apiCall('get_backlinks', { path }).then(res => {
                                    if (res && res.enabled === false) {
                                        if (cardContainer && cardContainer.parentNode) {
                                            cardContainer.remove();
                                        }
                                        return;
                                    }

                                    if (!res || !res.success || !Array.isArray(res.backlinks) || res.backlinks.length === 0) {
                                        noBacklinksMsg.style.display = 'block';
                                        return;
                                    }

                                    const backlinks = res.backlinks;
                                    listContainer.innerHTML = backlinks.map(b => {
                                        const subtitle = (b.title && b.title !== b.path) ? `<span style="color: var(--pw-text-muted); font-size: 0.8em; margin-left: 6px;">(${escapeHtml(b.path)})</span>` : '';
                                        return `<div class="pw-backlink-row" data-path="${escapeHtml(b.path)}">
                                            <div style="display: flex; align-items: center; gap: 8px; min-width: 0; overflow: hidden;">
                                                <iconify-icon icon="mdi:file-document-outline" style="color: var(--pw-text-muted); flex-shrink: 0;"></iconify-icon>
                                                <span style="min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-size: 0.875rem;">
                                                    <strong style="font-weight: 500;">${escapeHtml(b.title || b.path)}</strong>${subtitle}
                                                </span>
                                            </div>
                                            <iconify-icon icon="mdi:chevron-right" style="color: var(--pw-text-muted); flex-shrink: 0;"></iconify-icon>
                                        </div>`;
                                    }).join('');

                                    listContainer.onclick = (e) => {
                                        const row = e.target.closest('.pw-backlink-row');
                                        if (!row) return;
                                        const targetPath = row.getAttribute('data-path') || '';
                                        const cleanPath = (targetPath === '/' || targetPath.startsWith('/_')) ? targetPath : targetPath.replace(/^\//, '');
                                        const slashedPath = targetPath.startsWith('/') ? targetPath : '/' + targetPath;

                                        const treeItem = document.querySelector(`.pw-tree-item[data-path="${cleanPath}"], .pw-tree-item[data-path="${slashedPath}"]`);
                                        if (treeItem) {
                                            let parentNode = treeItem.closest('.pw-tree-node');
                                            while (parentNode) {
                                                parentNode.classList.add('pw-expanded');
                                                parentNode = parentNode.parentElement ? parentNode.parentElement.closest('.pw-tree-node') : null;
                                            }
                                            treeItem.click();
                                            treeItem.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                                        } else {
                                            sessionStorage.setItem('pw_active_page', cleanPath);
                                            window.location.reload();
                                        }
                                    };

                                    if (backlinks.length > 5) {
                                        toggleWrapper.style.display = 'block';
                                        const chevron = toggleBtn.querySelector('iconify-icon');
                                        toggleBtn.onclick = () => {
                                            const isExpanded = listContainer.classList.toggle('pw-expanded');
                                            if (chevron) {
                                                chevron.setAttribute('icon', isExpanded ? 'mdi:chevron-up' : 'mdi:chevron-down');
                                            }
                                            toggleBtn.setAttribute('title', isExpanded ? __('dashboard.show_less_backlinks') : __('dashboard.show_more_backlinks'));
                                        };
                                    }
                                });
                            }
                        }

                        // Comments section for dashboard (left column)
                        if (!isSystemPage && !isSnippet) {
                            const commentsTpl = document.getElementById('tpl-comments-card');
                            if (commentsTpl) {
                                const card = commentsTpl.content.cloneNode(true);
                                const listContainer = card.querySelector('[data-field="comments-list"]');
                                const noCommentsMsg = card.querySelector('[data-field="no-comments"]');
                                const badge = card.querySelector('[data-field="pending-count"]');

                                colLeft.appendChild(card);

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

                        // Remove empty column if no card
                        if (colRight.children.length === 0) {
                            colRight.remove();
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

        // Check for backlinks
        if (!currentSelectedPath.startsWith('/_snippets/')) {
            const backlinkRes = await apiCall('get_backlinks', { path: currentSelectedPath });
            if (backlinkRes && backlinkRes.enabled !== false && backlinkRes.success && Array.isArray(backlinkRes.backlinks) && backlinkRes.backlinks.length > 0) {
                const linksListHtml = backlinkRes.backlinks.map(b =>
                    `<li style="margin-bottom: 6px;"><strong>${escapeHtml(b.title || b.path)}</strong> <span style="color: var(--pw-text-muted); font-size: 0.8em;">(${escapeHtml(b.path)})</span></li>`
                ).join('');

                const warningHtml = `
                    <div style="margin-bottom: 12px; color: var(--pw-danger, #e53e3e); font-weight: 500; display: flex; align-items: center; gap: 6px;">
                        <iconify-icon icon="mdi:alert-circle-outline" style="font-size: 1.3em; flex-shrink: 0;"></iconify-icon>
                        <span>${escapeHtml(__('dashboard.delete_page_has_backlinks_desc'))}</span>
                    </div>
                    <ul style="max-height: 160px; overflow-y: auto; margin-bottom: 15px; border: 1px solid var(--pw-border); border-radius: var(--pw-radius, 4px); padding: 10px 14px 10px 30px; background: var(--pw-surface-light, rgba(255,255,255,0.02));">
                        ${linksListHtml}
                    </ul>
                    <p style="margin: 0; font-size: 0.85em; color: var(--pw-text-muted);">${escapeHtml(__('dashboard.confirm_delete_anyway'))}</p>
                `;

                const confirmed = await openDialog({
                    title: __('dashboard.delete_page_has_backlinks_title'),
                    html: warningHtml,
                    type: 'confirm',
                    confirmText: __('dashboard.delete_anyway') || __('common.delete'),
                    cancelText: __('common.cancel')
                });

                if (!confirmed) {
                    return;
                }

                const result = await apiSafe('delete_page', { path: currentSelectedPath });
                if (result) {
                    sessionStorage.setItem('pw-notify', JSON.stringify({text: __('dashboard.page_deleted'), type: 'success'}));
                    window.location.reload();
                } else {
                    notify(__('dashboard.error_delete_page'), 'error');
                }
                return;
            }
        }

        if (!isDeleteConfirm) {
            isDeleteConfirm = true;
            btnDelete.textContent = __('common.confirm');
            btnDelete.classList.add('pw-confirm-state');
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
            const section = targetItem.closest('.pw-sidebar-section');
            if (section) {
                section.classList.add('pw-expanded');
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

    initSidebarResizer();
}

/**
 * Initializes draggable resizer for the dashboard sidebar
 */
function initSidebarResizer() {
    const sidebar = document.querySelector('.pw-dashboard-sidebar');
    if (!sidebar || sidebar.classList.contains('pw-sidebar-fixed') || sidebar.hasAttribute('data-no-resize')) return;

    if (sidebar.querySelector('.pw-sidebar-resizer')) return;

    const minWidth = 180;
    const getMaxWidth = () => Math.min(700, Math.floor(window.innerWidth * 0.6));

    // Restore saved width from localStorage on desktop
    const savedWidth = localStorage.getItem('pw-sidebar-width');
    if (savedWidth && window.innerWidth > 800) {
        const parsed = parseInt(savedWidth, 10);
        if (!isNaN(parsed) && parsed >= minWidth && parsed <= getMaxWidth()) {
            sidebar.style.width = parsed + 'px';
            document.documentElement.style.setProperty('--pw-sidebar-width', parsed + 'px');
        }
    }

    const resizer = document.createElement('div');
    resizer.className = 'pw-sidebar-resizer';
    resizer.id = 'pw-sidebar-resizer';
    resizer.setAttribute('role', 'separator');
    resizer.setAttribute('aria-label', 'Resize sidebar');
    resizer.title = 'Drag to resize sidebar (double-click to reset)';
    resizer.innerHTML = '<div class="pw-sidebar-resizer-handle"><iconify-icon icon="mdi:drag-vertical"></iconify-icon></div>';

    // Blue highlight on hover
    resizer.addEventListener('mouseenter', () => {
        sidebar.classList.add('pw-resizer-hover');
    });
    resizer.addEventListener('mouseleave', () => {
        if (!sidebar.classList.contains('pw-is-resizing')) {
            sidebar.classList.remove('pw-resizer-hover');
        }
    });

    // Reset width on double click
    resizer.addEventListener('dblclick', (e) => {
        e.preventDefault();
        const defaultWidth = 250;
        sidebar.style.width = defaultWidth + 'px';
        document.documentElement.style.setProperty('--pw-sidebar-width', defaultWidth + 'px');
        localStorage.removeItem('pw-sidebar-width');
    });

    // Dragging logic
    resizer.addEventListener('mousedown', (e) => {
        if (e.button !== 0) return;
        if (window.innerWidth <= 800) return;
        e.preventDefault();

        const startX = e.clientX;
        const startWidth = sidebar.getBoundingClientRect().width;
        const maxWidth = getMaxWidth();

        sidebar.classList.add('pw-is-resizing');
        document.body.classList.add('pw-sidebar-resizing');

        let currentWidth = startWidth;
        let animationFrameId = null;

        const onMouseMove = (moveEvent) => {
            const deltaX = moveEvent.clientX - startX;
            let newWidth = Math.round(startWidth + deltaX);
            if (newWidth < minWidth) newWidth = minWidth;
            if (newWidth > maxWidth) newWidth = maxWidth;

            currentWidth = newWidth;
            if (!animationFrameId) {
                animationFrameId = requestAnimationFrame(() => {
                    sidebar.style.width = currentWidth + 'px';
                    document.documentElement.style.setProperty('--pw-sidebar-width', currentWidth + 'px');
                    animationFrameId = null;
                });
            }
        };

        const onMouseUp = () => {
            if (animationFrameId) {
                cancelAnimationFrame(animationFrameId);
                animationFrameId = null;
            }

            sidebar.style.width = currentWidth + 'px';
            document.documentElement.style.setProperty('--pw-sidebar-width', currentWidth + 'px');
            try {
                localStorage.setItem('pw-sidebar-width', currentWidth.toString());
            } catch (err) {}

            sidebar.classList.remove('pw-is-resizing');
            sidebar.classList.remove('pw-resizer-hover');
            document.body.classList.remove('pw-sidebar-resizing');

            document.removeEventListener('mousemove', onMouseMove);
            document.removeEventListener('mouseup', onMouseUp);
        };

        document.addEventListener('mousemove', onMouseMove);
        document.addEventListener('mouseup', onMouseUp);
    });

    sidebar.appendChild(resizer);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initSidebarResizer);
} else {
    initSidebarResizer();
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
