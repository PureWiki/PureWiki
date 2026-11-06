/**
 * PureWiki - Settings Logs
 *
 * Debug log viewer and activity log
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

/** Debug Log Viewer Logic */
function formatDebugLogBytes(bytes) {
    if (bytes < 1024) return bytes + ' B';
    return (bytes / 1024).toFixed(1) + ' KB';
}

function colorizeDebugLog(text) {
    return text
        .split('\n')
        .map(function (line) {
            var escaped = escapeHtml(line);
            if (/\[ERROR\]/.test(line)) return '<span class="pw-log-error">' + escaped + '</span>';
            if (/\[WARN\s*\]/.test(line)) return '<span class="pw-log-warn">' + escaped + '</span>';
            if (/\[INFO\s*\]/.test(line)) return '<span class="pw-log-info">' + escaped + '</span>';
            if (/\[DEBUG\]/.test(line)) return '<span class="pw-log-debug">' + escaped + '</span>';
            return escaped;
        })
        .join('\n');
}

async function loadDebugLog() {
    const output = document.getElementById('pw-debug-log-output');
    const select = document.getElementById('pw-debug-log-lines');
    const sizeInfo = document.getElementById('pw-debug-log-size-info');
    
    if (!output || !select || !sizeInfo) return;

    var lines = parseInt(select.value, 10) || 200;
    try {
        var data = await apiCall('get_debug_log', { lines: lines });
        if (window.PW_DEBUG) console.log('[debug-log] loaded', data);
        if (!data.success) return;
        var entries = data.log || [];
        output.innerHTML = entries.length
            ? colorizeDebugLog(entries.join('\n'))
            : __('settings.debug_log_empty');
        output.scrollTop = output.scrollHeight;
        if (data.size !== undefined) {
            sizeInfo.textContent = __('settings.debug_log_size') + ': ' + formatDebugLogBytes(data.size);
        }
    } catch (e) {
        if (window.PW_DEBUG) console.error('[debug-log] loadLog error', e);
    }
}

async function clearDebugLog() {
    const output = document.getElementById('pw-debug-log-output');
    const sizeInfo = document.getElementById('pw-debug-log-size-info');
    
    if (!output || !sizeInfo) return;

    var confirmed = await openDialog({
        title: __('settings.debug_log_clear'),
        text:  __('settings.debug_log_clear_confirm'),
        type: 'confirm'
    });
    if (!confirmed) return;
    
    try {
        var data = await apiCall('clear_debug_log', {});
        if (window.PW_DEBUG) console.log('[debug-log] cleared', data);
        if (!data.success) return;
        output.innerHTML = __('settings.debug_log_empty');
        sizeInfo.textContent = __('settings.debug_log_size') + ': 0 B';
        notify(__('settings.debug_log_cleared'), 'success');
    } catch (e) {
        if (window.PW_DEBUG) console.error('[debug-log] clearLog error', e);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const refresh = document.getElementById('pw-debug-log-refresh');
    const clear = document.getElementById('pw-debug-log-clear');
    const select = document.getElementById('pw-debug-log-lines');

    if (refresh) refresh.addEventListener('click', loadDebugLog);
    if (clear) clear.addEventListener('click', clearDebugLog);
    if (select) select.addEventListener('change', loadDebugLog);

    // Initial load if log viewer exists
    if (document.getElementById('pw-debug-log-output')) {
        loadDebugLog();
    }

    // Activity Log Controls
    const activityFilterAction = document.getElementById('pw-activity-filter-action');
    const activityFilterSearch = document.getElementById('pw-activity-filter-search');
    const activityRefreshBtn   = document.getElementById('pw-btn-activity-refresh');
    const activityLoadMoreBtn  = document.getElementById('pw-btn-activity-load-more');
    const clearActivityLogBtn  = document.getElementById('pw-btn-clear-activity-log');

    if (activityFilterAction) activityFilterAction.addEventListener('change', () => loadActivityLog(true));
    if (activityFilterSearch) {
        let searchTimeout;
        activityFilterSearch.addEventListener('input', () => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => loadActivityLog(true), 350);
        });
    }
    if (activityRefreshBtn) activityRefreshBtn.addEventListener('click', () => loadActivityLog(true));
    if (activityLoadMoreBtn) activityLoadMoreBtn.addEventListener('click', () => loadActivityLog(false));

    if (clearActivityLogBtn) {
        clearActivityLogBtn.addEventListener('click', async () => {
            const confirmed = await openDialog({
                title: __('settings.clear_log'),
                text: __('settings.clear_log_confirm'),
                confirmText: __('common.delete'),
                cancelText: __('common.cancel'),
                type: 'confirm'
            });
            if (confirmed) {
                const res = await apiSafe('clear_activity_log');
                if (res) {
                    notify(res.message, 'success');
                    loadActivityLog(true);
                }
            }
        });
    }
});

let activityOffset = 0;
const ACTIVITY_PAGE_LIMIT = 20;

/** Maps activity action identifiers to display badges and icons. */
function getActivityActionMeta(action, targetType) {
    switch (action) {
        case 'page_create':
            return { icon: 'mdi:file-plus-outline', badge: 'pw-badge-success', label: __('settings.activity_action_page_create') };
        case 'page_publish':
            return { icon: 'mdi:file-check-outline', badge: 'pw-badge-primary', label: __('settings.activity_action_page_publish') };
        case 'page_delete':
            return { icon: 'mdi:file-remove-outline', badge: 'pw-badge-danger', label: __('settings.activity_action_page_delete') };
        case 'page_move':
            return { icon: 'mdi:file-move-outline', badge: 'pw-badge-warning', label: __('settings.activity_action_page_move') };
        case 'media_upload':
            return { icon: 'mdi:upload-outline', badge: 'pw-badge-info', label: __('settings.activity_action_media_upload') };
        case 'media_delete':
            return { icon: 'mdi:image-off-outline', badge: 'pw-badge-danger', label: __('settings.activity_action_media_delete') };
        case 'comment_add':
            return { icon: 'mdi:comment-plus-outline', badge: 'pw-badge-primary', label: __('settings.activity_action_comment_add') };
        case 'user_login':
            return { icon: 'mdi:login', badge: 'pw-badge-info', label: __('settings.activity_action_user_login') };
        case 'page_restore':
            return { icon: 'mdi:history', badge: 'pw-badge-warning', label: __('settings.activity_action_page_restore') || 'Version Restored' };
        case 'user_create':
            return { icon: 'mdi:account-plus-outline', badge: 'pw-badge-success', label: __('settings.activity_action_user_create') || 'User Created' };
        case 'user_delete':
            return { icon: 'mdi:account-remove-outline', badge: 'pw-badge-danger', label: __('settings.activity_action_user_delete') || 'User Deleted' };
        case 'user_2fa_enable':
            return { icon: 'mdi:shield-check-outline', badge: 'pw-badge-success', label: __('settings.activity_action_user_2fa_enable') };
        case 'user_2fa_disable':
            return { icon: 'mdi:shield-off-outline', badge: 'pw-badge-warning', label: __('settings.activity_action_user_2fa_disable') };
        case 'comment_approve':
            return { icon: 'mdi:comment-check-outline', badge: 'pw-badge-success', label: __('settings.activity_action_comment_approve') || 'Comment Approved' };
        case 'comment_hide':
            return { icon: 'mdi:comment-eye-off-outline', badge: 'pw-badge-warning', label: __('settings.activity_action_comment_hide') || 'Comment Hidden' };
        case 'comment_delete':
            return { icon: 'mdi:comment-remove-outline', badge: 'pw-badge-danger', label: __('settings.activity_action_comment_delete') || 'Comment Deleted' };
        case 'clear_log':
            return { icon: 'mdi:delete-sweep-outline', badge: 'pw-badge-danger', label: __('settings.activity_action_clear_log') };
        case 'settings_update':
            return { icon: 'mdi:cog-outline', badge: 'pw-badge-secondary', label: __('settings.activity_action_settings_update') };
        default:
            return { icon: 'mdi:format-list-bulleted', badge: 'pw-badge-secondary', label: action };
    }
}

/** Loads and renders activity log items in the settings tab. */
async function loadActivityLog(reset = true) {
    const container = document.getElementById('pw-activity-log-container');
    const loadMoreWrap = document.getElementById('pw-activity-load-more-wrap');
    if (!container) return;

    if (reset) {
        activityOffset = 0;
        container.innerHTML = `<div class="pw-text-muted">${__('common.loading')}</div>`;
    }

    const actionFilter = document.getElementById('pw-activity-filter-action')?.value || 'all';
    const searchQuery  = document.getElementById('pw-activity-filter-search')?.value || '';

    const res = await apiSafe('get_activity_log', {
        limit: ACTIVITY_PAGE_LIMIT,
        offset: activityOffset,
        filter_action: actionFilter,
        filter_user: searchQuery
    }, { silent: true });

    if (reset) container.innerHTML = '';

    if (!res || !res.data || !Array.isArray(res.data.items) || res.data.items.length === 0) {
        if (reset) {
            container.innerHTML = `<div class="pw-text-muted" style="padding: 15px 0;">${__('settings.no_activities')}</div>`;
        }
        if (loadMoreWrap) loadMoreWrap.style.display = 'none';
        return;
    }

    const items = res.data.items;
    const total = res.data.total;

    items.forEach(item => {
        const meta = getActivityActionMeta(item.action, item.target_type);
        const card = document.createElement('div');
        card.className = 'pw-activity-item';

        const dateStr = item.timestamp ? new Date(item.timestamp).toLocaleString() : '—';
        const userStr = escapeHtml(item.user || 'system');
        const pathStr = item.target_path ? escapeHtml(item.target_path) : '';

        card.innerHTML = `
            <div class="pw-activity-left">
                <div class="pw-activity-icon">
                    <iconify-icon icon="${meta.icon}"></iconify-icon>
                </div>
                <div class="pw-activity-details">
                    <strong class="pw-activity-user">${userStr}</strong>
                    <span class="pw-badge ${meta.badge}">${meta.label}</span>
                    ${pathStr ? `<code class="pw-activity-path">${pathStr}</code>` : ''}
                </div>
            </div>
            <div class="pw-activity-date" title="${dateStr}">
                ${dateStr}
            </div>
        `;
        container.appendChild(card);
    });

    activityOffset += items.length;
    if (loadMoreWrap) {
        loadMoreWrap.style.display = (activityOffset < total) ? 'block' : 'none';
    }
}
