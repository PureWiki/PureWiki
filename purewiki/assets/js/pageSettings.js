/**
 * PureWiki - Page Settings Logic
 *
 * Handles loading and saving page-level settings on the configuration page
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

/**
 * Field definitions for page settings.
 * Each entry maps an API key to its DOM element ID and JSON data path.
 * An optional 'default' value - used when the field is not set in the page data.
 */
const pageSettings = [
    { key: 'description',        el: 'ps-description',        path: 'Description'                   },
    { key: 'tags',               el: 'ps-tags',               path: 'Tags'                          },
    { key: 'layout',             el: 'ps-layout',             path: 'Settings.Layout', default: 'page' },
    { key: 'is_private',         el: 'ps-is-private',         path: 'isPrivate'                     },
    { key: 'hide_in_treeview',   el: 'ps-hide-in-treeview',   path: 'Settings.hide_in_treeview'     },
    { key: 'include_in_navbar',  el: 'ps-include-in-navbar',  path: 'Settings.include_in_navbar'    },
    { key: 'navbar_link_text',   el: 'ps-navbar-link-text',   path: 'Settings.navbar_link_text'     },
    { key: 'prevnext_enabled',   el: 'ps-prevnext-enable',    path: 'Settings.prevnext_enabled'     },
    { key: 'hide_left_sidebar',  el: 'ps-hide-left-sidebar',  path: 'Settings.hide_left_sidebar'    },
    { key: 'hide_right_sidebar', el: 'ps-hide-right-sidebar', path: 'Settings.hide_right_sidebar'   },
];

let currentPageData = null;

/** Safely resolves a nested object property via dot-notation path */
function getNestedObjProp(obj, path) {
    return path.split('.').reduce((o, i) => (o ? o[i] : undefined), obj);
}

/** Fetches page data from the API and populates all form fields */
async function loadPageSettings(targetPath) {
    try {
        const result = await apiCall('get_page', { path: targetPath });

        if (result && result.success && result.data) {
            currentPageData = result.data;
        } else {
            notify(result.message || __('common.error'), 'error');
            return;
        }
    } catch (e) {
        notify(__('common.error'), 'error');
        return;
    }

    for (const field of pageSettings) {
        const el = document.getElementById(field.el);
        if (!el) continue;

        let val = getNestedObjProp(currentPageData, field.path);

        if (val === undefined) {
            val = field.default !== undefined ? field.default : (el.type === 'checkbox' ? false : '');
        }

        // Convert Tags array to comma-separated string for the UI
        if (field.key === 'tags' && Array.isArray(val)) val = val.join(', ');

        if (el.type === 'checkbox') {
            el.checked = !!val;
            el.dispatchEvent(new Event('change'));
        } else {
            el.value = val;
        }
    }

    // Auto-load values for extension fields (must have class "pw-ext-input" and name attribute matching the key in Settings)
    const extInputs = document.querySelectorAll('.pw-ext-input');
    extInputs.forEach(input => {
        if (!input.name) return;
        let val = getNestedObjProp(currentPageData, 'Settings.' + input.name);
        if (val === undefined) val = input.type === 'checkbox' ? false : '';
        
        if (input.type === 'checkbox') {
            input.checked = !!val;
            input.dispatchEvent(new Event('change'));
        } else {
            input.value = val;
        }
    });

    // Restore prevnext scope radio buttons
    const scopeVal = getNestedObjProp(currentPageData, 'Settings.prevnext_scope') || 'siblings';
    document.querySelectorAll('input[name="ps-prevnext-scope"]').forEach(r => r.checked = (r.value === scopeVal));

    // Sync prevnext options visibility
    const prevnextEnable  = document.getElementById('ps-prevnext-enable');
    const prevnextOptions = document.getElementById('ps-prevnext-options');
    if (prevnextEnable && prevnextOptions) {
        prevnextOptions.style.display = prevnextEnable.checked ? 'block' : 'none';
    }
}

/** Collects all form values and sends them to the API */
async function savePageSettings(targetPath) {
    if (!currentPageData) return;

    const btnSave = document.getElementById('pw-btn-save-page-settings');
    if (btnSave) btnSave.disabled = true;

    const params = { path: targetPath };

    for (const field of pageSettings) {
        const el = document.getElementById(field.el);
        if (!el) continue;
        params[field.key] = el.type === 'checkbox' ? (el.checked ? '1' : '0') : el.value.trim();
    }

    // Auto-save values for extension fields
    const extInputs = document.querySelectorAll('.pw-ext-input');
    extInputs.forEach(input => {
        if (!input.name) return;
        params[input.name] = input.type === 'checkbox' ? (input.checked ? '1' : '0') : input.value.trim();
    });

    // Append selected prevnext scope radio value
    const checkedRadio = document.querySelector('input[name="ps-prevnext-scope"]:checked');
    if (checkedRadio) params.prevnext_scope = checkedRadio.value;

    try {
        const result = await apiCall('save_page_settings', params);

        if (result && result.success) {
            notify(__('editor.page_settings_saved'), 'success');
            await loadPageSettings(targetPath);
        } else {
            notify(result.message || __('common.error'), 'error');
        }
    } catch (e) {
        notify(__('common.error'), 'error');
    } finally {
        if (btnSave) btnSave.disabled = false;
    }
}

document.addEventListener('DOMContentLoaded', async () => {
    const btnBack = document.getElementById('pw-btn-back');
    const btnSave = document.getElementById('pw-btn-save-page-settings');

    const urlParams    = new URLSearchParams(window.location.search);
    const targetPath   = urlParams.get('path') || '/';
    const fromLocation = urlParams.get('from') || 'dashboard';

    // Display the current page path in the header
    const pathLabel = document.getElementById('pw-ps-path-label');
    if (pathLabel) {
        pathLabel.textContent = targetPath;
        pathLabel.setAttribute('data-path', targetPath);
    }

    // prevnext toggle
    const prevnextEnable  = document.getElementById('ps-prevnext-enable');
    const prevnextOptions = document.getElementById('ps-prevnext-options');
    if (prevnextEnable && prevnextOptions) {
        prevnextEnable.addEventListener('change', (e) => {
            prevnextOptions.style.display = e.target.checked ? 'block' : 'none';
        });
    }

    if (btnSave) btnSave.addEventListener('click', () => savePageSettings(targetPath));

    if (btnBack) {
        btnBack.addEventListener('click', () => {
            window.location.href = fromLocation === 'editor'
                ? (window.PW_BASE_PATH || '') + '/dashboard/edit?path=' + encodeURIComponent(targetPath)
                : (window.PW_BASE_PATH || '') + '/dashboard';
        });
    }

    await loadPageSettings(targetPath);
});
