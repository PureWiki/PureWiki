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
let backupRequestActive = false;

// Constants for polling backup status
const UPDATE_BACKUP_POLL_ATTEMPTS = 60; //max attempts until timeout (pre-update backup)
const BACKUP_POLL_INTERVAL_MS = 5000; //check every x milliseconds
const PASSWORD_MASK = '********'; //mask string for password fields 

const settingsFields = {
    'wiki_name':     'pw-setting-wiki-name',
    'wiki_logo':     'pw-setting-wiki-logo',
    'wiki_favicon':  'pw-setting-wiki-favicon',
    'dashboard_language': 'pw-setting-dashboard-language',
    'dashboard_theme': 'pw-setting-dashboard-theme',
    'allowed_file_extensions': 'pw-setting-allowed-extensions',
    'open_external_links_new_tab': 'pw-setting-open-external-links-new-tab',
    'current_theme': 'pw-setting-current-theme',
    'enable_cache':  'pw-setting-enable-cache',
    'cache_lifetime':'pw-setting-cache-lifetime',
    'enable_backlinks': 'pw-setting-enable-backlinks',
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
    'search_page_enabled': 'pw-setting-search-page-enabled',
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
    'editor_show_math': 'pw-setting-editor-show-math',
    'editor_show_icon': 'pw-setting-editor-show-icon',
    'editor_show_video': 'pw-setting-editor-show-video',
    'dev_debug_output': 'pw-setting-dev-debug-output',
    'allow_prerelease_updates': 'pw-setting-allow-prerelease-updates',
    'mail_enable': 'pw-setting-mail-enable',
    'mail_host': 'pw-setting-mail-host',
    'mail_port': 'pw-setting-mail-port',
    'mail_username': 'pw-setting-mail-username',
    'mail_encryption': 'pw-setting-mail-encryption',
    'mail_from_address': 'pw-setting-mail-from-address',
    'mail_from_name': 'pw-setting-mail-from-name',
    'mail_notify_address': 'pw-setting-mail-notify-address',
    'mail_notify_comments': 'pw-setting-mail-notify-comments',
    'mail_notify_pages': 'pw-setting-mail-notify-pages',
    'mail_notify_publish': 'pw-setting-mail-notify-publish',
    'mail_notify_users': 'pw-setting-mail-notify-users',
    'mail_notify_security': 'pw-setting-mail-notify-security',
    'i18n_enabled':  'pw-setting-i18n-enabled',
    'i18n_default_lang': 'pw-setting-i18n-default-lang',
    'i18n_supported_langs': 'pw-setting-i18n-supported-langs',
    'comments_enabled': 'pw-setting-comments-enabled',
    'comments_require_approval': 'pw-setting-comments-require-approval',
    'comments_spam_regex': 'pw-setting-comments-spam-regex',
    'enable_activity_log': 'pw-setting-enable-activity-log'
};

/** Sets the theme color pickers to saved values or to theme defaults. */
function fillThemeColors(saved) {
    document.querySelectorAll('[data-theme-color]').forEach(input => {
        const value = saved && saved[input.dataset.themeColor] ? saved[input.dataset.themeColor][input.dataset.mode] : null;
        input.value = /^#[0-9a-f]{6}$/i.test(value || '') ? value : input.dataset.default;
    });
}

/** Returns theme colors as { var: { light, dark } }, null if nothing to save. */
function collectThemeColors() {
    const inputs = document.querySelectorAll('[data-theme-color]');
    if (inputs.length === 0) return null;

    const colors = document.getElementById('pw-theme-colors');
    if (colors && colors.style.display === 'none') return null;

    const values = {};
    const defaults = {};
    inputs.forEach(input => {
        const name = input.dataset.themeColor;
        values[name] = values[name] || {};
        defaults[name] = defaults[name] || {};
        values[name][input.dataset.mode] = input.value;
        defaults[name][input.dataset.mode] = input.dataset.default;
    });

    // Only store changed colors
    Object.keys(values).forEach(name => {
        if (values[name].light === defaults[name].light && values[name].dark === defaults[name].dark) {
            delete values[name];
        }
    });
    return values;
}

function initThemeColors() {
    document.querySelectorAll('[data-theme-color-reset]').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll(`[data-theme-color="${btn.dataset.themeColorReset}"]`).forEach(input => {
                input.value = input.dataset.default;
            });
        });
    });

    // Colors belong to saved theme, new theme needs a save and reload first
    const select = document.getElementById('pw-setting-current-theme');
    const hint = document.getElementById('pw-theme-colors-save-first');
    const colors = document.getElementById('pw-theme-colors');
    if (select && hint && colors) {
        select.addEventListener('change', () => {
            const changed = select.value !== hint.dataset.savedTheme;
            hint.style.display = changed ? '' : 'none';
            colors.style.display = changed ? 'none' : '';
        });
    }
}

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
                } else if (key === 'i18n_supported_langs' || key === 'comments_spam_regex') {
                    el.value = JSON.stringify(Array.isArray(result.data[key]) ? result.data[key] : []);
                    el.dispatchEvent(new Event('change'));
                } else {
                    el.value = result.data[key];
                }
            }
        }

        fillThemeColors(result.data.theme_colors);

        const rebuildField = document.getElementById('pw-field-rebuild-backlinks');
        if (rebuildField && result.data.enable_backlinks !== undefined) {
            const isEnabled = result.data.enable_backlinks === true || result.data.enable_backlinks === 'true';
            rebuildField.style.display = isEnabled ? 'block' : 'none';
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
            } else if (key === 'i18n_supported_langs' || key === 'comments_spam_regex') {
                try {
                    config[key] = el.value ? JSON.parse(el.value) : [];
                } catch(e) {
                    config[key] = [];
                }
            } else {
                config[key] = el.value;
            }
        }
    }

    const themeColors = collectThemeColors();
    if (themeColors) config.theme_colors = themeColors;

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
            pwEl.value = result.data.mail_password ? PASSWORD_MASK : '';
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
    if (pwEl && pwEl.value && pwEl.value !== PASSWORD_MASK) {
        config.mail_password = pwEl.value;
    }

    const result = await apiSafe('save_mail_config', { config: JSON.stringify(config) });

    if (result) {
        notify(__('settings.save_success'), 'success');
        const pwEl = document.getElementById('pw-setting-mail-password');
        if(pwEl && pwEl.value) {
            pwEl.value = PASSWORD_MASK;
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

        if (data.upload_limits) {
            const ul = data.upload_limits;
            const uploadEl = document.getElementById('pw-status-upload-limits');
            uploadEl.textContent = __('settings.upload_limits_summary', ul.upload_max_filesize || __('settings.upload_unlimited'), ul.post_max_size || __('settings.upload_unlimited'));
            if (ul.post_too_small) {
                const warning = document.createElement('div');
                warning.style.cssText = 'color: var(--pw-warning); font-size: 0.9em; margin-top: 4px;';
                warning.innerHTML = '<iconify-icon icon="mdi:alert-outline"></iconify-icon> ';
                warning.append(__('settings.upload_post_too_small'));
                uploadEl.appendChild(warning);
            }
        }

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

        if (data.storage) {
            const st = data.storage;
            const storageEl = document.getElementById('pw-status-storage');
            const storageTpl = document.getElementById('tpl-storage-stats');

            if (storageEl && storageTpl) {
                const node = storageTpl.content.cloneNode(true);
                const progressEl = node.querySelector('[data-field="progress"]');
                const ratioEl = node.querySelector('[data-field="ratio"]');
                const summaryEl = node.querySelector('[data-field="summary"]');
                const btnRecalc = node.querySelector('#pw-btn-recalculate-storage');

                if (progressEl) {
                    progressEl.value = st.disk_used_bytes;
                    progressEl.max = st.disk_total_bytes > 0 ? st.disk_total_bytes : 100;
                    if (st.is_low_space) {
                        progressEl.classList.add('pw-progress-danger');
                    } else {
                        progressEl.classList.remove('pw-progress-danger');
                    }
                }

                if (ratioEl) {
                    ratioEl.textContent = `${st.used_percent}%`;
                }

                if (summaryEl) {
                    summaryEl.textContent = __('settings.storage_summary', st.wiki_size_formatted, st.disk_free_formatted, st.disk_total_formatted);
                }

                if (btnRecalc) {
                    btnRecalc.addEventListener('click', async () => {
                        btnRecalc.disabled = true;
                        btnRecalc.innerHTML = '<iconify-icon icon="line-md:loading-loop"></iconify-icon>';
                        await loadSystemStatus();
                    });
                }

                storageEl.innerHTML = '';
                storageEl.appendChild(node);
            }

            const alertsEl = document.getElementById('pw-status-alerts');
            if (alertsEl) {
                const existingLowAlert = alertsEl.querySelector('[data-field="storage-alert-text"]');
                if (st.is_low_space) {
                    if (!existingLowAlert) {
                        const alertDiv = document.createElement('div');
                        alertDiv.style.cssText = 'padding: 12px; background: rgba(244, 67, 54, 0.1); border-left: 4px solid var(--pw-danger); border-radius: 4px; color: var(--pw-danger); font-size: 0.9em; margin-bottom: 10px;';
                        alertDiv.innerHTML = `<strong><iconify-icon icon="mdi:alert-circle-outline"></iconify-icon> Info:</strong> <span data-field="storage-alert-text">${__('settings.storage_low_alert')}</span>`;
                        alertsEl.appendChild(alertDiv);
                    }
                } else if (existingLowAlert) {
                    const alertContainer = existingLowAlert.closest('div');
                    if (alertContainer) alertContainer.remove();
                }
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
    const startBackupBtn = document.getElementById('pw-btn-start-backup');
    const bulkWebpBtn = document.getElementById('pw-btn-bulk-webp');
    const checkUpdatesBtn = document.getElementById('pw-btn-check-updates');
    const startUpdateBtn = document.getElementById('pw-btn-start-update');
    const mailEnableToggle = document.getElementById('pw-setting-mail-enable');
    const sendTestMailBtn = document.getElementById('pw-btn-send-test-mail');

    let usersLoaded = false;
    let backupsLoaded = false;
    let extensionsLoaded = false;
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
    initThemeColors();

    const cacheToggle = document.getElementById('pw-setting-enable-cache');
    const cacheLifetime = document.getElementById('pw-setting-cache-lifetime');
    if (cacheToggle && cacheLifetime) {
        const updateCacheState = () => {
            cacheLifetime.disabled = !cacheToggle.checked;
            cacheLifetime.style.opacity = cacheToggle.checked ? '1' : '0.5';
        };
        cacheToggle.addEventListener('change', updateCacheState);
        updateCacheState();
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
        updateHistoryState();
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

    // i18n Settings Logic
    const i18nInput = document.getElementById('pw-setting-i18n-supported-langs');
    const i18nList = document.getElementById('pw-i18n-lang-list');
    const i18nNewLang = document.getElementById('pw-i18n-new-lang');
    const i18nAddBtn = document.getElementById('pw-btn-add-lang');
    const i18nToggle = document.getElementById('pw-setting-i18n-enabled');
    const i18nGroup = document.getElementById('pw-setting-i18n-group');
    const i18nDefault = document.getElementById('pw-setting-i18n-default-lang');

    if (i18nInput && i18nList && i18nAddBtn) {
        const renderLangs = () => {
            i18nList.innerHTML = '';
            let langs = [];
            try { langs = JSON.parse(i18nInput.value); } catch(e) {}
            if (!Array.isArray(langs)) langs = [];

            langs.forEach(lang => {
                const badge = document.createElement('span');
                badge.className = 'pw-lang-badge';
                badge.innerHTML = escapeHtml(lang) + ' <iconify-icon icon="mdi:close" title="Entfernen"></iconify-icon>';
                badge.querySelector('iconify-icon').addEventListener('click', () => {
                    const newLangs = langs.filter(l => l !== lang);
                    i18nInput.value = JSON.stringify(newLangs);
                    renderLangs();
                });
                i18nList.appendChild(badge);
            });
        };

        i18nInput.addEventListener('change', renderLangs);

        const addLang = () => {
            const val = i18nNewLang.value.trim().toLowerCase();
            if (val) {
                let langs = [];
                try { langs = JSON.parse(i18nInput.value); } catch(e) {}
                if (!langs.includes(val)) {
                    langs.push(val);
                    i18nInput.value = JSON.stringify(langs);
                    renderLangs();
                }
                i18nNewLang.value = '';
            }
        };

        i18nAddBtn.addEventListener('click', addLang);
        i18nNewLang.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                addLang();
            }
        });

        if (i18nToggle) {
            const updateI18nState = () => {
                const disabled = !i18nToggle.checked;
                i18nGroup.style.opacity = disabled ? '0.5' : '1';
                i18nNewLang.disabled = disabled;
                i18nAddBtn.disabled = disabled;
                i18nDefault.disabled = disabled;
                i18nDefault.style.opacity = disabled ? '0.5' : '1';
            };
            i18nToggle.addEventListener('change', updateI18nState);
            updateI18nState();
        }
    }

    // Comments Spam Regex settings logic
    const spamRegexInput = document.getElementById('pw-setting-comments-spam-regex');
    const spamRegexList = document.getElementById('pw-comments-spam-list');
    const spamRegexNew = document.getElementById('pw-comments-spam-new-regex');
    const spamRegexAddBtn = document.getElementById('pw-btn-add-spam-regex');
    const commentsToggle = document.getElementById('pw-setting-comments-enabled');
    const commentsRequireApproval = document.getElementById('pw-setting-comments-require-approval');
    const commentsSpamGroup = document.getElementById('pw-setting-comments-spam-group');

    if (spamRegexInput && spamRegexList && spamRegexAddBtn) {
        const renderSpamRegex = () => {
            spamRegexList.innerHTML = '';
            let patterns = [];
            try { patterns = JSON.parse(spamRegexInput.value); } catch(e) {}
            if (!Array.isArray(patterns)) patterns = [];

            patterns.forEach(pattern => {
                const badge = document.createElement('span');
                badge.className = 'pw-lang-badge';
                badge.style.textTransform = 'none'; // Prevent forcing uppercase
                badge.innerHTML = escapeHtml(pattern) + ' <iconify-icon icon="mdi:close" title="Entfernen"></iconify-icon>';
                badge.querySelector('iconify-icon').addEventListener('click', () => {
                    const newPatterns = patterns.filter(p => p !== pattern);
                    spamRegexInput.value = JSON.stringify(newPatterns);
                    renderSpamRegex();
                });
                spamRegexList.appendChild(badge);
            });
        };

        spamRegexInput.addEventListener('change', renderSpamRegex);

        const addSpamRegex = () => {
            const val = spamRegexNew.value.trim();
            if (val) {
                let patterns = [];
                try { patterns = JSON.parse(spamRegexInput.value); } catch(e) {}
                if (!patterns.includes(val)) {
                    patterns.push(val);
                    spamRegexInput.value = JSON.stringify(patterns);
                    renderSpamRegex();
                }
                spamRegexNew.value = '';
            }
        };

        spamRegexAddBtn.addEventListener('click', addSpamRegex);
        spamRegexNew.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                addSpamRegex();
            }
        });

        if (commentsToggle && commentsRequireApproval && commentsSpamGroup) {
            const updateCommentsState = () => {
                const disabled = !commentsToggle.checked;
                commentsRequireApproval.disabled = disabled;
                commentsRequireApproval.style.opacity = disabled ? '0.5' : '1';
                
                commentsSpamGroup.style.opacity = disabled ? '0.5' : '1';
                const spamInputs = commentsSpamGroup.querySelectorAll('input, button');
                spamInputs.forEach(input => {
                    input.disabled = disabled;
                });
            };
            commentsToggle.addEventListener('change', updateCommentsState);
            updateCommentsState();
        }
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

            // Lazy-load extensions when Extensions tab is opened
            if (targetId === 'extensions' && !extensionsLoaded) {
                loadExtensions();
                extensionsLoaded = true;
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
                    statusInterval = setInterval(checkBackupStatus, BACKUP_POLL_INTERVAL_MS);
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

            // Activity Tab loading
            if (targetId === 'activity') {
                loadActivityLog(true);
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

    const viewNotesBtn = document.getElementById('pw-btn-view-releasenotes');
    if (viewNotesBtn) {
        viewNotesBtn.addEventListener('click', () => {
             if (currentRelease && currentRelease.release_notes) {
                  openDialog({
                      title: currentRelease.release_name || __('settings.release_notes') || 'Release Notes',
                      html: currentRelease.release_notes,
                      type: 'alert'
                  });
             }
        });
    }
});
