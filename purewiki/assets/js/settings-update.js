/**
 * PureWiki - Settings Update
 *
 * Update tab of the settings. Checks for new releases and runs the update
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

/** Loads the current update status. */
async function loadUpdateStatus() {
    const statusText = document.getElementById('pw-update-status-text');
    if (statusText) statusText.textContent = __('settings.checking_updates');
    checkForUpdates();
}

/** Checks GitHub for new releases. */
async function checkForUpdates() {
    const statusText = document.getElementById('pw-update-status-text');
    const metaEl = document.getElementById('pw-update-meta');
    const newVerEl = document.getElementById('pw-update-new-version');
    const changelogLink = document.getElementById('pw-link-changelog');
    const startBtn = document.getElementById('pw-btn-start-update');
    const viewNotesBtn = document.getElementById('pw-btn-view-releasenotes');

    const res = await apiSafe('check_for_updates', {});
    if (res && res.data) {
        const d = res.data;
        if (d.update_available) {
            statusText.innerHTML = '<strong>' + __('settings.update_available') + '</strong> ' + __('settings.update_available_desc');
            const badgeType = d.is_prerelease ? __('settings.prerelease_badge') : __('settings.stable_badge');
            const badgeColor = d.is_prerelease ? 'var(--pw-warning)' : 'var(--pw-success)';
            const badgeHtml = `<span style="background: ${badgeColor}; color: #fff; padding: 2px 6px; border-radius: 4px; font-size: 0.8em; margin-left: 8px;">${badgeType}</span>`;
            newVerEl.innerHTML = d.latest_version + badgeHtml;
            
            metaEl.style.display = 'block';

            if (changelogLink) {
                changelogLink.style.display = 'inline-flex';
            }
            if (viewNotesBtn) {
                viewNotesBtn.style.display = 'inline-flex';
            }

            startBtn.style.display = 'inline-flex';
            currentRelease = d;
        } else {
            statusText.textContent = __('settings.up_to_date', d.current_version);
            metaEl.style.display = 'none';
            if (changelogLink) changelogLink.style.display = 'none';
            if (viewNotesBtn) viewNotesBtn.style.display = 'none';
            startBtn.style.display = 'none';
        }
    } else if (statusText) {
        statusText.textContent = __('settings.failed_check_updates');
    }
}

/** Orchestrates the multi-step update process. */
async function runUpdate(release) {
    const overlay = document.getElementById('pw-update-progress-overlay');
    const bar = document.getElementById('pw-update-progress-bar');
    const title = document.getElementById('pw-update-step-title');
    const desc = document.getElementById('pw-update-step-desc');

    const setProgress = (val, t, d) => {
        bar.value = val;
        title.textContent = t;
        desc.textContent = d;
    };

    const confirmed = await openDialog({
        title: __('settings.update_purewiki'),
        text: __('settings.update_confirm', release.latest_version),
        type: 'confirm',
        confirmText: __('settings.start_update'),
        cancelText: __('common.cancel')
    });

    if (!confirmed) return;

    overlay.style.display = 'block';
    document.getElementById('pw-update-container').style.opacity = '0.5';
    document.getElementById('pw-btn-check-updates').disabled = true;
    document.getElementById('pw-btn-start-update').disabled = true;

    let isUpdating = true;
    // Block page reload while update is running
    const blockUnload = (e) => {
        if (!isUpdating) return;
        e.preventDefault();
        e.returnValue = 'update';
        return 'update';
    };
    window.addEventListener('beforeunload', blockUnload);

    try {
        // Check Requirements
        setProgress(10, __('settings.checking_requirements'), __('settings.checking_requirements_desc'));
        const reqRes = await apiSafe('get_update_requirements');
        if (!reqRes) throw new Error(__('settings.error_update_requirements'));
        if (!reqRes.data.all_critical_met) {
            throw new Error(__('settings.requirements_not_met'));
        }

        // Pre-Update Backup
        setProgress(30, __('settings.creating_backup'), __('settings.creating_backup_desc'));
        const backupRes = await apiSafe('start_pre_update_backup');
        if (!backupRes) throw new Error(__('settings.update_backup_failed'));

        // Poll for backup completion
        await new Promise((resolve, reject) => {
            let attempts = 0;
            const check = async () => {
                const status = await apiSafe('get_update_backup_status', {}, { silent: true });
                if (status && !status.running) {
                    resolve();
                } else if (attempts > UPDATE_BACKUP_POLL_ATTEMPTS) {
                    reject(new Error(__('settings.backup_timeout')));
                } else {
                    attempts++;
                    setTimeout(check, BACKUP_POLL_INTERVAL_MS);
                }
            };
            check();
        });

        // Download Update
        setProgress(60, __('settings.downloading_package'), __('settings.downloading_package_desc'));
        const dlRes = await apiSafe('download_update', { zip_url: release.zip_url });
        if (!dlRes) throw new Error(__('settings.failed_download_update'));

        if (!dlRes.data.checksum_verified) {
            const installAnyway = await openDialog({
                title: __('settings.update_unverified_title'),
                text: __('settings.update_unverified_text'),
                type: 'confirm',
                confirmText: __('settings.update_install_anyway'),
                cancelText: __('common.cancel')
            });
            if (!installAnyway) {
                await apiSafe('cleanup_update', {}, { silent: true });
                throw Object.assign(new Error(), { cancelled: true });
            }
        }

        // Installation
        setProgress(85, __('settings.installing_update'), __('settings.installing_update_desc'));
        const instRes = await apiSafe('install_update');
        if (!instRes) throw new Error(__('settings.failed_install_update'));

        // Finalize
        setProgress(100, __('settings.update_complete'), __('settings.update_complete_desc'));
        notify(__('settings.update_successful'), 'success');

        setTimeout(() => {
            isUpdating = false;
            window.removeEventListener('beforeunload', blockUnload);
            window.location.reload();
        }, 3000);

    } catch (err) {
        isUpdating = false;
        overlay.style.display = 'none';
        document.getElementById('pw-update-container').style.opacity = '1';
        document.getElementById('pw-btn-check-updates').disabled = false;
        document.getElementById('pw-btn-start-update').disabled = false;
        window.removeEventListener('beforeunload', blockUnload);

        if (err.cancelled) {
            notify(__('settings.update_cancelled'), 'info');
            return;
        }

        openDialog({
            title: __('settings.update_failed'),
            text: err.message,
            type: 'alert'
        });
    }
}
