/**
 * PureWiki - Settings Helper
 *
 * Logik for managing global dashboard settings. Handles loading values
 * from the API and saving changes back to the configuration file.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

let currentRelease = null;

const settingsFields = {
    'wiki_name':     'pw-setting-wiki-name',
    'wiki_logo':     'pw-setting-wiki-logo',
    'wiki_favicon':  'pw-setting-wiki-favicon',
    'dashboard_language': 'pw-setting-dashboard-language',
    'dashboard_theme': 'pw-setting-dashboard-theme',
    'allowed_file_extensions': 'pw-setting-allowed-extensions',
    'current_theme': 'pw-setting-current-theme',
    'enable_cache':  'pw-setting-enable-cache',
    'cache_lifetime':'pw-setting-cache-lifetime',
    'breadcrumbs_show_start_page': 'pw-setting-breadcrumbs-show-start-page',
    'enable_admin_menu': 'pw-setting-enable-admin-menu',
    'custom_css': 'pw-setting-custom-css',
    'custom_html_head': 'pw-setting-custom-html-head',
    'custom_html_footer': 'pw-setting-custom-html-footer',
    'custom_js_head': 'pw-setting-custom-js-head',
    'custom_js_footer': 'pw-setting-custom-js-footer',
    'seo_prevent_indexing': 'pw-setting-seo-prevent-index',
    'wiki_description': 'pw-setting-wiki-description',
    'seo_title_format': 'pw-setting-seo-title-format',
    'seo_auto_opengraph': 'pw-setting-seo-opengraph',
    'seo_og_image_url': 'pw-setting-seo-og-image',
    'seo_enable_sitemap': 'pw-setting-seo-sitemap',
    'seo_auto_canonical': 'pw-setting-seo-auto-canonical',
    'seo_twitter_cards': 'pw-setting-seo-twitter',
    'seo_schema_org': 'pw-setting-seo-schema-org',
    'search_max_results': 'pw-setting-search-max-results',
    'enable_history': 'pw-setting-enable-history',
    'history_max_versions': 'pw-setting-history-max-versions',
    'editor_show_raw': 'pw-setting-editor-show-raw',
    'editor_show_markdown': 'pw-setting-editor-show-markdown',
    'editor_show_liveMarkdown': 'pw-setting-editor-show-liveMarkdown',
    'editor_show_code': 'pw-setting-editor-show-code',
    'editor_show_table': 'pw-setting-editor-show-table',
    'editor_show_inlineCode': 'pw-setting-editor-show-inlineCode',
    'editor_show_underline': 'pw-setting-editor-show-underline',
    'editor_show_pagelist': 'pw-setting-editor-show-pagelist',
    'editor_show_toc': 'pw-setting-editor-show-toc',
    'editor_show_callout': 'pw-setting-editor-show-callout',
    'editor_show_block': 'pw-setting-editor-show-block',
    'editor_show_pageinclude': 'pw-setting-editor-show-pageinclude',
    'editor_show_snippet': 'pw-setting-editor-show-snippet',
    'editor_show_accordion': 'pw-setting-editor-show-accordion',
    'editor_show_grid': 'pw-setting-editor-show-grid',
    'dev_debug_output': 'pw-setting-dev-debug-output',
    'allow_prerelease_updates': 'pw-setting-allow-prerelease-updates',
    'mail_enable': 'pw-setting-mail-enable',
    'mail_host': 'pw-setting-mail-host',
    'mail_port': 'pw-setting-mail-port',
    'mail_username': 'pw-setting-mail-username',
    'mail_encryption': 'pw-setting-mail-encryption',
    'mail_from_address': 'pw-setting-mail-from-address',
    'mail_from_name': 'pw-setting-mail-from-name'
};

/** Loads the current configuration and populates the form fields. */
async function loadSettings() {
    const result = await apiSafe('get_config', {}, { silent: true });

    if (result && result.data) {
        for (const [key, elId] of Object.entries(settingsFields)) {
            if (key.startsWith('mail_')) continue; // Skip mail settings for main config

            const el = document.getElementById(elId);
            if (el && result.data[key] !== undefined) {
                if (el.type === 'checkbox') {
                    el.checked = result.data[key] === true || result.data[key] === 'true';
                    el.dispatchEvent(new Event('change'));
                } else {
                    el.value = result.data[key];
                }
            }
        }
    }
}

async function saveSettings() {
    const config = {};
    for (const [key, elId] of Object.entries(settingsFields)) {
        const el = document.getElementById(elId);
        if (el) {
            if (el.type === 'checkbox') {
                config[key] = el.checked;
            } else {
                config[key] = el.value;
            }
        }
    }

    const result = await apiSafe('save_config', { config: JSON.stringify(config) });

    if (result) {
        notify(__('settings.save_success'), 'success');
    }
}

async function loadMailSettings() {
    const result = await apiSafe('get_mail_config', {}, { silent: true });
    if (result && result.data) {
        for (const [key, elId] of Object.entries(settingsFields)) {
            if (!key.startsWith('mail_')) continue;
            const el = document.getElementById(elId);
            if (el && result.data[key] !== undefined) {
                if (el.type === 'checkbox') {
                    el.checked = result.data[key] === true || result.data[key] === 'true';
                    el.dispatchEvent(new Event('change'));
                } else {
                    el.value = result.data[key];
                }
            }
        }
        const pwEl = document.getElementById('pw-setting-mail-password');
        if(pwEl) {
            pwEl.value = result.data.mail_password ? '********' : '';
        }

        toggleMailGroup(document.getElementById('pw-setting-mail-enable').checked);
    }
}

async function saveMailSettings() {
    const config = {};
    for (const [key, elId] of Object.entries(settingsFields)) {
        if (!key.startsWith('mail_')) continue;
        const el = document.getElementById(elId);
        if (el) {
            if (el.type === 'checkbox') {
                config[key] = el.checked;
            } else {
                config[key] = el.value;
            }
        }
    }
    const pwEl = document.getElementById('pw-setting-mail-password');
    if (pwEl && pwEl.value && pwEl.value !== '********') {
        config.mail_password = pwEl.value;
    }

    const result = await apiSafe('save_mail_config', { config: JSON.stringify(config) });

    if (result) {
        notify(__('settings.save_success'), 'success');
        const pwEl = document.getElementById('pw-setting-mail-password');
        if(pwEl && pwEl.value) {
            pwEl.value = '********';
        }
    }
}

function toggleMailGroup(isEnabled) {
    const group = document.getElementById('pw-mail-settings-group');
    if (group) {
        group.style.opacity = isEnabled ? '1' : '0.5';
        const inputs = group.querySelectorAll('input, select, button');
        inputs.forEach(input => {
            input.disabled = !isEnabled;
        });
    }
}

/** Loads and displays system status and environment info. */
async function loadSystemStatus() {
    const result = await apiSafe('get_system_status', {}, { silent: true });
    if (result && result.data) {
        const data = result.data;
        document.getElementById('pw-status-version').textContent = data.version;
        document.getElementById('pw-status-php-version').textContent = data.php_version;
        document.getElementById('pw-status-os').textContent = data.os;

        const webpEl = document.getElementById('pw-status-webp');
        const actionsEl = document.getElementById('pw-status-actions');
        const statsEl = document.getElementById('pw-status-media-stats');

        if (data.webp_enabled) {
            webpEl.innerHTML = '';
            const enabledSpan = document.createElement('span');
            enabledSpan.style.color = 'var(--pw-success)';
            enabledSpan.innerHTML = `<iconify-icon icon="mdi:check-circle-outline"></iconify-icon> ${__('settings.webp_enabled', data.webp_engine)}`;
            webpEl.appendChild(enabledSpan);
            actionsEl.style.display = 'block';
        } else {
            webpEl.innerHTML = '';
            const disabledSpan = document.createElement('span');
            disabledSpan.style.color = 'var(--pw-danger)';
            disabledSpan.innerHTML = '<iconify-icon icon="mdi:alert-circle-outline"></iconify-icon> ' + __('settings.webp_not_supported');
            webpEl.appendChild(disabledSpan);
            actionsEl.style.display = 'none';

            const alertsEl = document.getElementById('pw-status-alerts');
            const alertTpl = document.getElementById('tpl-webp-alert');
            if (alertsEl && alertTpl) {
                // Remove existing webp alerts to avoid duplicates, don't wipe everything
                const existingAlert = alertsEl.querySelector('[data-field="webp-alert-text"]');
                if (!existingAlert) {
                    const alertNode = alertTpl.content.cloneNode(true);
                    const textSpan = alertNode.querySelector('[data-field="webp-alert-text"]');
                    if (textSpan) {
                        textSpan.textContent = __('settings.webp_alert');
                    }
                    alertsEl.appendChild(alertNode);
                }
            }
        }

        if (data.stats) {
            const s = data.stats;
            const statsTpl = document.getElementById('tpl-media-stats');
            if (statsTpl) {
                const stats = statsTpl.content.cloneNode(true);
                stats.querySelector('[data-field="progress"]').value = s.webp_optimized;
                stats.querySelector('[data-field="progress"]').max = s.total_images;
                stats.querySelector('[data-field="ratio"]').textContent = s.optimization_ratio + '%';
                stats.querySelector('[data-field="summary"]').textContent = __('settings.images_optimized', s.webp_optimized, s.total_images);
                statsEl.innerHTML = '';
                statsEl.appendChild(stats);
            }
        }
    }
}

/** Checks if sensitive files are accessible from outside. */
async function checkSecurityConfiguration() {
    try {
        const response = await fetch('/config/config.json', { method: 'HEAD' });

        // If the response is OK (200), the protected file is accessible
        if (response.ok) {
            const alertsEl = document.getElementById('pw-status-alerts');
            const alertTpl = document.getElementById('tpl-security-alert');

            if (alertsEl && alertTpl) {
                if (!alertsEl.querySelector('[data-field="security-alert-text"]')) {
                    const alertNode = alertTpl.content.cloneNode(true);

                    const titleSpan = alertNode.querySelector('[data-field="security-alert-title"]');
                    if (titleSpan) titleSpan.textContent = __('settings.security_warning_title');

                    const textSpan = alertNode.querySelector('[data-field="security-alert-text"]');
                    if (textSpan) textSpan.textContent = __('settings.security_warning');

                    alertsEl.insertBefore(alertNode, alertsEl.firstChild);
                }
            }
        }
    } catch (err) {
        // Ignore fetch errors, could be network or explicitly blocked by CORS/etc.
    }
}

/** Check status of the WebP conversion process. */
async function checkWebpStatus() {
    const btn = document.getElementById('pw-btn-bulk-webp');
    if (!btn) return false;

    const res = await apiSafe('get_bulk_webp_status', {}, { silent: true });
    if (res && res.data) {
        if (res.data.running) {
            btn.disabled = true;
            btn.innerHTML = '<iconify-icon icon="line-md:loading-loop"></iconify-icon> ' + __('settings.optimizing');
            return true;
        } else {
            if (btn.disabled) {
                btn.disabled = false;
                btn.innerHTML = '<iconify-icon icon="mdi:auto-fix"></iconify-icon> ' + __('settings.optimize_all');
                loadSystemStatus();
            }
            return false;
        }
    }
    return false;
}

document.addEventListener('DOMContentLoaded', () => {
    const tabs = document.querySelectorAll('.pw-tree-item');
    const tabContents = document.querySelectorAll('.pw-settings-tab');
    const saveBtn = document.getElementById('pw-btn-save-settings');
    const addUserForm = document.getElementById('pw-form-add-user');
    const clearCacheBtn = document.getElementById('pw-btn-clear-cache');
    const startBackupBtn = document.getElementById('pw-btn-start-backup');
    const bulkWebpBtn = document.getElementById('pw-btn-bulk-webp');
    const checkUpdatesBtn = document.getElementById('pw-btn-check-updates');
    const startUpdateBtn = document.getElementById('pw-btn-start-update');
    const mailEnableToggle = document.getElementById('pw-setting-mail-enable');
    const sendTestMailBtn = document.getElementById('pw-btn-send-test-mail');

    let usersLoaded = false;
    let backupsLoaded = false;
    let statusInterval = null;
    let webpInterval = null;

    const startWebpPolling = () => {
        if (webpInterval) return;
        webpInterval = setInterval(async () => {
            const isRunning = await checkWebpStatus();
            if (!isRunning) {
                clearInterval(webpInterval);
                webpInterval = null;
            }
        }, 3000);
    };

    loadSettings();
    loadMailSettings();

    const cacheToggle = document.getElementById('pw-setting-enable-cache');
    const cacheLifetime = document.getElementById('pw-setting-cache-lifetime');
    if (cacheToggle && cacheLifetime) {
        const updateCacheState = () => {
            cacheLifetime.disabled = !cacheToggle.checked;
            cacheLifetime.style.opacity = cacheToggle.checked ? '1' : '0.5';
        };
        cacheToggle.addEventListener('change', updateCacheState);
        setTimeout(updateCacheState, 500); // Small delay to ensure API values are applied
    }

    // History Settings Toggle
    const historyToggle = document.getElementById('pw-setting-enable-history');
    const historyMax = document.getElementById('pw-setting-history-max-versions');
    if (historyToggle && historyMax) {
        const updateHistoryState = () => {
            historyMax.disabled = !historyToggle.checked;
            historyMax.style.opacity = historyToggle.checked ? '1' : '0.5';
        };
        historyToggle.addEventListener('change', updateHistoryState);
        setTimeout(updateHistoryState, 500); // Small delay to ensure API values are applied
    }

    if (bulkWebpBtn) {
        bulkWebpBtn.addEventListener('click', async () => {
            const res = await apiSafe('start_bulk_webp');
            if (res) {
                notify(res.message, 'success');
                startWebpPolling();
            }
        });
    }

    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            tabs.forEach(t => t.classList.remove('pw-tree-active'));
            tab.classList.add('pw-tree-active');

            tabContents.forEach(content => {
                content.style.display = 'none';
            });

            const targetId = tab.getAttribute('data-settings-tab');
            const targetContent = document.getElementById('pw-tab-' + targetId);
            if (targetContent) {
                targetContent.style.display = 'block';
            }

            // Lazy-load user list when User Management tab is opened
            if (targetId === 'users' && !usersLoaded) {
                loadUsers();
                usersLoaded = true;
            }

            // Backup Tab specific logic (Loading & Polling)
            if (targetId === 'backup') {
                if (!backupsLoaded) {
                    loadBackups();
                    backupsLoaded = true;
                }

                checkBackupStatus(); // Check immediately

                // Start polling if not already active
                if (!statusInterval) {
                    statusInterval = setInterval(checkBackupStatus, 5000);
                }
            } else {
                // Stop polling when leaving the backup tab
                if (statusInterval) {
                    clearInterval(statusInterval);
                    statusInterval = null;
                }
            }

            // Status Tab loading
            if (targetId === 'status') {
                loadSystemStatus();
                loadUpdateStatus();
                checkSecurityConfiguration();
                checkWebpStatus().then(isRunning => {
                    if (isRunning) startWebpPolling();
                });
            } else {
                if (webpInterval) {
                    clearInterval(webpInterval);
                    webpInterval = null;
                }
            }
        });
    });

    if (saveBtn) {
        saveBtn.addEventListener('click', () => {
            saveSettings();
            if (document.getElementById('pw-tab-mail').style.display === 'block') {
                saveMailSettings();
            }
        });
    }

    if (mailEnableToggle) {
        mailEnableToggle.addEventListener('change', async (e) => {
            // Prevent programmatic changes (like loadMailSettings()) from triggering the confirm dialog
            if (!e.isTrusted) return;

            const isEnabled = e.target.checked;
            if (!isEnabled) {
                const confirmed = await openDialog({
                    title: __('settings.mail_disable_title'),
                    text: __('settings.mail_disable_confirm'),
                    type: 'confirm',
                    confirmText: __('settings.mail_disable_btn'),
                    cancelText: __('common.cancel')
                });

                if (!confirmed) {
                    e.target.checked = true;
                    return;
                }

                const result = await apiSafe('disable_mail');
                if(result) {
                    notify(__('settings.mail_disabled_success'), 'success');
                    loadMailSettings(); // reset UI
                }
            } else {
                 toggleMailGroup(true);
            }
        });
    }

    if (sendTestMailBtn) {
        sendTestMailBtn.addEventListener('click', async () => {
            const testEmail = document.getElementById('pw-setting-test-mail-address').value.trim();
            if (!testEmail) {
                notify(__('settings.test_mail_missing'), 'error');
                return;
            }

            sendTestMailBtn.disabled = true;
            sendTestMailBtn.innerHTML = '<iconify-icon icon="line-md:loading-loop"></iconify-icon> ' + __('common.loading');

            const res = await apiSafe('send_test_mail', { email: testEmail });

            sendTestMailBtn.disabled = false;
            sendTestMailBtn.innerHTML = '<iconify-icon icon="mdi:send" class="pw-icon-left"></iconify-icon> ' + __('settings.send_test_mail');

            if (res) {
                if (res.success) {
                    notify(__('settings.test_mail_sent'), 'success');
                } else {
                    notify(res.message, 'error');
                }
            }
        });
    }

    if (addUserForm) {
        addUserForm.addEventListener('submit', addUser);
    }

    if (startBackupBtn) {
        startBackupBtn.addEventListener('click', startBackup);
    }

    if (checkUpdatesBtn) {
        checkUpdatesBtn.addEventListener('click', checkForUpdates);
    }

    if (startUpdateBtn) {
        startUpdateBtn.addEventListener('click', () => {
            if (currentRelease) runUpdate(currentRelease);
        });
    }
});

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
    const changelogEl = document.getElementById('pw-update-changelog');
    const changelogLink = document.getElementById('pw-link-changelog');
    const startBtn = document.getElementById('pw-btn-start-update');

    const res = await apiSafe('check_for_updates', {});
    if (res && res.data) {
        const d = res.data;
        if (d.update_available) {
            statusText.innerHTML = '<strong>' + __('settings.update_available') + '</strong> ' + __('settings.update_available_desc');
            const badgeType = d.is_prerelease ? __('settings.prerelease_badge') : __('settings.stable_badge');
            const badgeColor = d.is_prerelease ? 'var(--pw-warning)' : 'var(--pw-success)';
            const badgeHtml = `<span style="background: ${badgeColor}; color: #fff; padding: 2px 6px; border-radius: 4px; font-size: 0.8em; margin-left: 8px;">${badgeType}</span>`;
            newVerEl.innerHTML = d.latest_version + badgeHtml;
            changelogEl.textContent = d.release_notes.substring(0, 300) + (d.release_notes.length > 300 ? '...' : '');
            metaEl.style.display = 'block';

            if (changelogLink) {
                changelogLink.href = d.html_url;
                changelogLink.style.display = 'inline-flex';
            }

            startBtn.style.display = 'inline-flex';
            currentRelease = d;
        } else {
            statusText.textContent = __('settings.up_to_date', d.current_version);
            metaEl.style.display = 'none';
            if (changelogLink) changelogLink.style.display = 'none';
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
        await apiSafe('start_pre_update_backup');

        // Poll for backup completion
        await new Promise((resolve, reject) => {
            let attempts = 0;
            const check = async () => {
                const status = await apiSafe('get_update_backup_status', {}, { silent: true });
                if (status && !status.running) {
                    resolve();
                } else if (attempts > 60) { // 5 minutes timeout
                    reject(new Error(__('settings.backup_timeout')));
                } else {
                    attempts++;
                    setTimeout(check, 5000);
                }
            };
            check();
        });

        // Download Update
        setProgress(60, __('settings.downloading_package'), __('settings.downloading_package_desc'));
        const dlRes = await apiSafe('download_update', { zip_url: release.zip_url });
        if (!dlRes) throw new Error(__('settings.failed_download_update'));

        // Installation
        setProgress(85, __('settings.installing_update'), __('settings.installing_update_desc'));
        const instRes = await apiSafe('install_update');
        if (!instRes) throw new Error(__('settings.failed_install_update'));

        // Finalize
        setProgress(100, __('settings.update_complete'), __('settings.update_complete_desc'));
        notify(__('settings.update_successful'), 'success');

        setTimeout(() => {
            window.location.reload();
        }, 3000);

    } catch (err) {
        overlay.style.display = 'none';
        document.getElementById('pw-update-container').style.opacity = '1';
        document.getElementById('pw-btn-check-updates').disabled = false;
        document.getElementById('pw-btn-start-update').disabled = false;

        openDialog({
            title: __('settings.update_failed'),
            text: err.message,
            type: 'alert'
        });
    }
}

/** Fetches the user list and renders it in the table. */
async function loadUsers() {
    const tbody = document.getElementById('pw-user-list');
    if (!tbody) return;

    tbody.innerHTML = '<tr><td colspan="2" style="padding: 12px; color: var(--pw-text-muted);">' + __('common.loading') + '</td></tr>';

    const result = await apiSafe('list_users', {}, { silent: true });

    if (result && result.data) {
        renderUserList(result.data);
    } else {
        tbody.innerHTML = '<tr><td colspan="2" style="padding: 12px; color: var(--pw-danger);">' + __('settings.failed_load_users') + '</td></tr>';
    }
}

/**
 * Renders the user list into the table body.
 * @param {Array} users - Array of user objects with username and created_at.
 */
function renderUserList(users) {
    const tbody = document.getElementById('pw-user-list');
    if (!tbody) return;

    if (users.length === 0) {
        tbody.innerHTML = '<tr><td colspan="2" style="padding: 12px; color: var(--pw-text-muted);">' + __('settings.no_users') + '</td></tr>';
        return;
    }

    const isSingleUser = users.length === 1;
    const rowTpl = document.getElementById('tpl-user-row');
    tbody.innerHTML = '';

    users.forEach(user => {
        const row = rowTpl.content.cloneNode(true);
        row.querySelector('[data-field="username"]').textContent = user.username;
        row.querySelector('[data-field="role"]').textContent = user.role || 'admin';
        const btn = row.querySelector('.pw-user-delete-btn');
        btn.setAttribute('data-username', user.username);
        if (isSingleUser) {
            btn.disabled = true;
            btn.title = __('settings.cannot_delete_only_user');
        } else {
            btn.addEventListener('click', () => deleteUserEntry(user.username));
        }
        tbody.appendChild(row);
    });
}

/** Deletes a user via the API and reloads the list. */
async function deleteUserEntry(username) {
    const result = await apiSafe('delete_user', { username });

    if (result) {
        notify(__('settings.user_deleted'), 'success');
        loadUsers();
    } else {
        notify(__('settings.error_delete_user'), 'error');
    }
}

/** Creates a new user via the API and reloads the list. */
async function addUser(e) {
    if (e) e.preventDefault();
    const usernameEl = document.getElementById('pw-new-username');
    const passwordEl = document.getElementById('pw-new-password');
    const roleEl = document.getElementById('pw-new-role');
    if (!usernameEl || !passwordEl) return;

    const username = usernameEl.value.trim();
    const password = passwordEl.value;
    const role = roleEl ? roleEl.value : 'admin';

    if (!username || !password) {
        notify(__('settings.username_password_required'), 'error');
        return;
    }

    const result = await apiSafe('create_user', { username, password, role });

    if (result) {
        notify(__('settings.user_created'), 'success');
        usernameEl.value = '';
        passwordEl.value = '';
        if (roleEl) roleEl.value = 'admin';
        loadUsers();
    }
}

/** Safely escapes HTML entities string (prevent XSS). */
function escapeHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

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

    backups.forEach(backup => {
        const row = rowTpl.content.cloneNode(true);
        row.querySelector('[data-field="date"]').textContent = backup.date;
        row.querySelector('[data-field="size"]').textContent = backup.size;
        row.querySelector('.pw-backup-download-link').href = `/purewiki/api.php?action=download_backup&file=${encodeURIComponent(backup.file)}`;
        const deleteBtn = row.querySelector('.pw-backup-delete-btn');
        deleteBtn.setAttribute('data-file', backup.file);
        deleteBtn.addEventListener('click', () => deleteBackupEntry(backup.file, deleteBtn));
        tbody.appendChild(row);
    });
}

/** Starts a new backup in the background. */
async function startBackup() {
    const btn = document.getElementById('pw-btn-start-backup');

    const result = await apiSafe('start_backup');

    if (result) {
        notify(__('settings.backup_started'), 'success');
        // Update button state immediately
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<iconify-icon icon="line-md:loading-loop" style="margin-right: 5px;"></iconify-icon> ' + __('settings.backup_progress');
        }
    }
}

/** Checks if a backup is currently running and updates the UI. */
async function checkBackupStatus() {
    const btn = document.getElementById('pw-btn-start-backup');
    if (!btn) return;

    const result = await apiSafe('get_backup_status', {}, { silent: true });
    if (result) {
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

