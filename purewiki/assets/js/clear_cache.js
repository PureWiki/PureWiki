/**
 * PureWiki - Cache Clear Event Handler
 *
 * Handles cache and backlinks clearing actions within the dashboard
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

document.addEventListener('DOMContentLoaded', () => {
    const btnClearCache = document.getElementById('pw-btn-clear-cache');
    if (btnClearCache) {
        btnClearCache.addEventListener('click', async () => {
            const res = await apiSafe('clear_cache');
            if (res) {
                notify(__('settings.cache_cleared'), 'success');
            } else {
                notify(__('settings.failed_clear_cache'), 'error');
            }
        });
    }

    const btnRebuildBacklinks = document.getElementById('pw-btn-rebuild-backlinks');
    if (btnRebuildBacklinks) {
        btnRebuildBacklinks.addEventListener('click', async () => {
            btnRebuildBacklinks.disabled = true;
            const res = await apiSafe('rebuild_backlinks');
            btnRebuildBacklinks.disabled = false;
            if (res && res.success) {
                notify(__('settings.backlinks_rebuilt'), 'success');
            } else {
                notify(__('settings.failed_rebuild_backlinks'), 'error');
            }
        });
    }
});