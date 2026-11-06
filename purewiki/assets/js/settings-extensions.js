/**
 * PureWiki - Settings Extensions
 *
 * Extensions tab of the settings. Lists, toggles and uninstalls extensions
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

/** Fetches the extensions list and renders it. */
async function loadExtensions() {
    const list = document.getElementById('pw-extensions-list');
    if (!list) return;

    list.innerHTML = '<div><iconify-icon icon="line-md:loading-loop"></iconify-icon> ' + __('common.loading') + '</div>';

    const result = await apiSafe('list_extensions', {}, { silent: true });

    if (result && result.extensions) {
        renderExtensionList(result.extensions);
    } else {
        list.innerHTML = '<div style="color: var(--pw-danger);">' + __('settings.error_load_extensions') + '</div>';
    }
}

/**
 * Renders the extension list
 * @param {Array} extensions
 */
function renderExtensionList(extensions) {
    const list = document.getElementById('pw-extensions-list');
    if (!list) return;

    if (!extensions || extensions.length === 0) {
        list.innerHTML = '<div style="color: var(--pw-text-muted);">' + __('settings.no_extensions_found') + '</div>';
        return;
    }

    const tpl = document.getElementById('tpl-extension-card');
    list.innerHTML = '';

    extensions.forEach(ext => {
        const card = tpl.content.cloneNode(true);
        card.querySelector('[data-field="name"]').textContent = ext.name;
        card.querySelector('[data-field="version"]').textContent = ext.version;
        card.querySelector('[data-field="author"]').textContent = ext.author;
        card.querySelector('[data-field="description"]').textContent = ext.description;

        const urlEl = card.querySelector('[data-field="url"]');
        if (ext.url) {
            urlEl.href = ext.url;
        } else {
            urlEl.style.display = 'none';
        }

        const badge = card.querySelector('[data-field="status-badge"]');
        const toggleBtn = card.querySelector('.pw-btn-ext-toggle');
        const uninstallBtn = card.querySelector('.pw-btn-ext-uninstall');

        if (ext.enabled) {
            badge.textContent = __('settings.ext_enabled');
            badge.style.background = 'var(--pw-success)';
            badge.style.color = '#fff';
            toggleBtn.innerHTML = '<iconify-icon icon="mdi:toggle-switch" style="font-size: 1.2em; vertical-align: middle;"></iconify-icon> ' + __('settings.ext_disable');
            toggleBtn.classList.add('pw-btn-secondary');
        } else {
            badge.textContent = __('settings.ext_disabled');
            badge.style.background = 'var(--pw-text-muted)';
            badge.style.color = '#fff';
            toggleBtn.innerHTML = '<iconify-icon icon="mdi:toggle-switch-off-outline" style="font-size: 1.2em; vertical-align: middle;"></iconify-icon> ' + __('settings.ext_enable');
            toggleBtn.classList.add('pw-btn-primary');
        }

        toggleBtn.addEventListener('click', () => toggleExtension(ext.id, !ext.enabled));
        uninstallBtn.addEventListener('click', () => uninstallExtension(ext.id, ext.name));

        list.appendChild(card);
    });
}

/** Toggles an extension on/off */
async function toggleExtension(id, enable) {
    const res = await apiSafe('toggle_extension', { extension_id: id, enabled: enable });
    if (res && res.success) {
        notify(__('settings.save_success'), 'success');
        loadExtensions();
        // Reload to register/deregister PHP hooks and tabs
        setTimeout(() => window.location.reload(), 1000);
    }
}

/** Uninstalls an extension */
async function uninstallExtension(id, name) {
    const confirmed = await openDialog({
        title: __('settings.ext_uninstall_title', name),
        text: __('settings.ext_uninstall_confirm', name),
        type: 'confirm',
        confirmText: __('common.delete'),
        cancelText: __('common.cancel')
    });

    if (confirmed) {
        const res = await apiSafe('uninstall_extension', { extension_id: id });
        if (res && res.success) {
            notify(__('settings.ext_uninstalled'), 'success');
            loadExtensions();
            setTimeout(() => window.location.reload(), 1000);
        }
    }
}
