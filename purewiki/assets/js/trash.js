/**
 * PureWiki - Trash Manager
 *
 * Handles listing, restoring, and deleting trashed pages.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

document.addEventListener('DOMContentLoaded', () => {
    initDialogSystem();
    initTrash();
});

function initTrash() {
    loadTrashItems();
    initEmptyTrashButton();
}

/** Gets and renders the list of trashed pages. */
async function loadTrashItems() {
    const wrapper = document.getElementById('pw-trash-list-wrapper');
    if (!wrapper) return;

    const res = await apiSafe('list_trash');
    if (!res?.success) {
        wrapper.innerHTML = `<p class="pw-hint">${__('common.error')}</p>`;
        return;
    }

    const items = res.items ?? [];

    if (items.length === 0) {
        wrapper.innerHTML = `<p class="pw-trash-empty-hint pw-hint">${__('dashboard.trash_empty')}</p>`;
        return;
    }

    const tpl = document.getElementById('tpl-trash-row');
    wrapper.innerHTML = `
        <table class="pw-settings-table pw-trash-table">
            <thead>
                <tr>
                    <th>${__('dashboard.page_info')}</th>
                    <th>${__('dashboard.trash_deleted_at')}</th>
                    <th>${__('dashboard.trash_sub_pages')}</th>
                    <th class="pw-text-right">${__('settings.actions')}</th>
                </tr>
            </thead>
            <tbody id="pw-trash-tbody"></tbody>
        </table>`;

    const tbody = document.getElementById('pw-trash-tbody');

    for (const item of items) {
        const row = tpl.content.cloneNode(true);
        row.querySelector('[data-field="title"]').textContent        = item.title;
        row.querySelector('[data-field="original-slug"]').textContent = '/' + item.original_slug;
        row.querySelector('[data-field="deleted-at"]').textContent   = item.deleted_at
            ? new Date(item.deleted_at).toLocaleString()
            : '—';
        row.querySelector('[data-field="children"]').textContent = item.children_count > 0
            ? item.children_count
            : '—';

        row.querySelector('.pw-trash-restore-btn').addEventListener('click', () => restoreItem(item.slug, item.title));
        row.querySelector('.pw-trash-delete-btn').addEventListener('click',  () => deleteItem(item.slug, item.title));

        tbody.appendChild(row);
    }
}

/**
 * Restores a trashed page back into the pages directory.
 * @param {string} slug The trash entry slug (original-slug__timestamp).
 * @param {string} title title for the dialog.
 */
async function restoreItem(slug, title) {
    const res = await apiSafe('restore_trash_item', { slug });
    loadTrashItems();
    if (!res?.success) {
        showToast(__('dashboard.error_trash_restore'), 'error');
        return;
    }

    if (res.renamed) {
        showToast(__('dashboard.trash_restore_renamed').replace('%s', res.new_slug), 'success');
    } else {
        showToast(__('dashboard.trash_restored'), 'success');
    }
}

/**
 * Permanently deletes a single trash item.
 * @param {string} slug The trash entry slug.
 * @param {string} title title for the dialog.
 */
async function deleteItem(slug, title) {
    const confirmed = await openDialog({
        title:   __('dashboard.trash_delete_permanent'),
        text: __('dashboard.trash_confirm_delete').replace('%s', title),
        type:    'confirm'
    });

    if (confirmed) {
        const res = await apiSafe('delete_trash_item', { slug });
        loadTrashItems();
        if (!res?.success) {
            showToast(__('dashboard.error_trash_delete'), 'error');
            return;
        }
        showToast(__('dashboard.trash_deleted'), 'success');
    }
}

/** Two-step "Empty Trash" button logic. */
function initEmptyTrashButton() {
    const btn = document.getElementById('pw-btn-empty-trash');
    if (!btn) return;

    let confirmPending = false;
    let resetTimer     = null;

    btn.addEventListener('click', async () => {
        if (!confirmPending) {
            confirmPending = true;
            btn.classList.add('pw-btn-empty-confirm');
            btn.innerHTML = `<iconify-icon icon="mdi:alert"></iconify-icon> ${__('dashboard.trash_empty_confirm_btn')}`;

            resetTimer = setTimeout(() => {
                confirmPending = false;
                btn.classList.remove('pw-btn-empty-confirm');
                btn.innerHTML = `<iconify-icon icon="mdi:delete-sweep"></iconify-icon> ${__('dashboard.trash_empty_btn')}`;
            }, 5000);
            return;
        }

        clearTimeout(resetTimer);
        confirmPending = false;
        btn.classList.remove('pw-btn-empty-confirm');
        btn.disabled = true;

        const res = await apiSafe('empty_trash');
        btn.disabled = false;
        btn.innerHTML = `<iconify-icon icon="mdi:delete-sweep"></iconify-icon> ${__('dashboard.trash_empty_btn')}`;
        loadTrashItems();

        if (!res?.success) {
            showToast(__('dashboard.error_trash_empty'), 'error');
            return;
        }
        showToast(__('dashboard.trash_emptied').replace('%s', res.deleted), 'success');
    });
}
