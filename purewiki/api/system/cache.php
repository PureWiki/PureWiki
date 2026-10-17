<?php
/**
 * PureWiki - Cache Management
 *
 * Handles clearing and status of the system cache. Triggers the removal
 * of all cached HTML fragments and backlink index.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

if ($action === 'clear_cache') {
    clearCache();
    $response['success'] = true;
    $response['message'] = 'Cache cleared.';
} elseif ($action === 'rebuild_backlinks') {
    require_once __DIR__ . '/../../core/backlinks.php';
    if (!isBacklinksEnabled()) {
        $response['message'] = __('settings.backlinks_disabled_notice');
        return;
    }
    $index = buildBacklinksIndex();
    $response['success'] = true;
    $response['message'] = __('settings.backlinks_rebuilt');
    $response['indexed_pages'] = $index['indexed_pages'] ?? 0;
    $response['backlink_targets'] = count($index['backlinks'] ?? []);
}