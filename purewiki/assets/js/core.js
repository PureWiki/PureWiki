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
let currentDialogId = 0;

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
        currentDialogId++;

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
        const closingId = currentDialogId;

        const cleanup = () => {
            if (dialogBox) {
                dialogBox.removeEventListener('animationend', cleanup);
            }
            if (closingId !== currentDialogId) return;
            overlay.classList.remove('pw-show', 'pw-closing');
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
    const btnChangeName = document.getElementById('pw-btn-change-display-name');

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

    if (btnChangeName) {
        btnChangeName.addEventListener('click', (e) => {
            e.stopPropagation();
            toggleMenu(false);
            openChangeDisplayNameDialog(btnChangeName);
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

/** Opens dialog to change the public display name of the current user */
async function openChangeDisplayNameDialog(button) {
    if (!document.getElementById('pw-dialog-overlay')) {
        initDialogSystem();
    }

    const current = button.dataset.displayName || '';
    const html = `
        <div style="display: flex; flex-direction: column; gap: 8px; margin-top: 5px;">
            <label for="pw-user-display-name" style="display: block; font-size: 13px; color: var(--pw-text-muted);">${__('auth.display_name_hint')}</label>
            <input id="pw-user-display-name" type="text" class="pw-input pw-w-full" maxlength="100" value="${escapeHtml(current)}" placeholder="${__('auth.display_name_placeholder')}">
        </div>
    `;

    const confirmed = await openDialog({
        title: __('auth.change_display_name'),
        html: html,
        type: 'confirm',
        confirmText: __('common.save'),
        cancelText: __('common.cancel')
    });

    if (!confirmed) return;

    const input = document.getElementById('pw-user-display-name');
    const displayName = input ? input.value.trim() : '';
    if (displayName === current) return;

    const res = await apiSafe('change_display_name', { display_name: displayName });
    if (res && res.success !== false) {
        button.dataset.displayName = displayName;
        notify(res.message || __('auth.display_name_changed'), 'success');
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
