/**
 * PureWiki - Settings Backups
 *
 * Backup tab of the settings. Lists, creates and deletes backups
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

/** Fetches the backup list and renders it in the table. */
async function loadBackups() {
    const tbody = document.getElementById('pw-backup-list');
    if (!tbody) return;

    tbody.innerHTML = '<tr><td colspan="3" style="padding: 12px; color: var(--pw-text-muted);">' + __('settings.loading_backups') + '</td></tr>';

    const result = await apiSafe('list_backups', {}, { silent: true });

    if (result && result.data) {
        renderBackupList(result.data);
    } else {
        tbody.innerHTML = '<tr><td colspan="3" style="padding: 12px; color: var(--pw-text-muted);">' + __('settings.no_backups') + '</td></tr>';
    }
}

/**
 * Renders the backup list into the table body.
 * @param {Array} backups - Array of backup objects with file, size, date.
 */
function renderBackupList(backups) {
    const tbody = document.getElementById('pw-backup-list');
    if (!tbody) return;

    if (!backups || backups.length === 0) {
        tbody.innerHTML = '<tr><td colspan="3" style="padding: 12px; color: var(--pw-text-muted);">' + __('settings.no_backups') + '</td></tr>';
        return;
    }

    const rowTpl = document.getElementById('tpl-backup-row');
    tbody.innerHTML = '';

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    backups.forEach(backup => {
        const row = rowTpl.content.cloneNode(true);
        row.querySelector('[data-field="date"]').textContent = backup.date;
        row.querySelector('[data-field="size"]').textContent = backup.size;
        row.querySelector('.pw-backup-download-link').href = `${window.PW_BASE_PATH || ''}/purewiki/api.php?action=download_backup&file=${encodeURIComponent(backup.file)}&csrf_token=${encodeURIComponent(csrfToken || '')}`;
        const deleteBtn = row.querySelector('.pw-backup-delete-btn');
        deleteBtn.setAttribute('data-file', backup.file);
        deleteBtn.addEventListener('click', () => deleteBackupEntry(backup.file, deleteBtn));
        tbody.appendChild(row);
    });
}

/** Creates a new backup and reloads the list when it is done. */
async function startBackup() {
    const btn = document.getElementById('pw-btn-start-backup');
    if (!btn || btn.disabled) return;

    btn.disabled = true;
    btn.innerHTML = '<iconify-icon icon="line-md:loading-loop" style="margin-right: 5px;"></iconify-icon> ' + __('settings.backup_progress');

    // The request only returns when the backup is done, apiSafe() shows the error message on failure
    backupRequestActive = true;
    const result = await apiSafe('start_backup');
    backupRequestActive = false;
    if (result) {
        notify(__('settings.backup_finished'), 'success');
    }

    loadBackups();
    btn.disabled = false;
    btn.innerHTML = '<iconify-icon icon="mdi:zip-box-outline" style="margin-right: 5px;"></iconify-icon> ' + __('settings.start_backup');
}

/** Checks if a backup is currently running and updates the UI. */
async function checkBackupStatus() {
    const btn = document.getElementById('pw-btn-start-backup');
    // startBackup() handles the button itself while its request runs
    if (!btn || backupRequestActive) return;

    const result = await apiSafe('get_backup_status', {}, { silent: true });
    if (result && !backupRequestActive) {
        if (result.running) {
            btn.disabled = true;
            btn.innerHTML = '<iconify-icon icon="line-md:loading-loop" style="margin-right: 5px;"></iconify-icon> ' + __('settings.backup_progress');
        } else {
            // If it was just running and now stopped, reload the list
            if (btn.disabled) {
                loadBackups();
                notify(__('settings.backup_finished'), 'success');
            }
            btn.disabled = false;
            btn.innerHTML = '<iconify-icon icon="mdi:zip-box-outline" style="margin-right: 5px;"></iconify-icon> ' + __('settings.start_backup');
        }
    }
}

/**
 * Calls the API to delete a backup, then reloads the list.
 * @param {string} file
 * @param {HTMLElement} btn The button element that was clicked.
 */
async function deleteBackupEntry(file, btn) {
    const confirmed = await openDialog({
        title: __('settings.delete_backup'),
        text: __('settings.delete_backup_confirm', file),
        type: 'confirm',
        confirmText: __('common.delete'),
        cancelText: __('common.cancel')
    });

    if (!confirmed) return;

    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<iconify-icon icon="line-md:loading-loop"></iconify-icon>';
    }

    const result = await apiSafe('delete_backup', { file });

    if (result) {
        notify(__('settings.backup_deleted'), 'success');
        loadBackups();
    } else {
        notify(__('settings.error_delete_backup'), 'error');
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<iconify-icon icon="mdi:delete-outline"></iconify-icon>';
        }
    }
}
