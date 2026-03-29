/**
 * PureWiki - Cache Clear Event Handler
 *
 * Handles cache clearing actions within the dashboard
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
});
