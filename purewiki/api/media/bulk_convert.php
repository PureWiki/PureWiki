<?php
/**
 * PureWiki - Bulk WebP Conversion API
 *
 * API actions for triggering and checking the status of background WebP image conversion.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

if ($action === 'start_bulk_webp') {
    $lockFile = getCacheDir() . '/webp_conversion.lock';

    if (isLockActive($lockFile)) {
        $response['success'] = false;
        $response['message'] = 'A conversion process is already running.';
        return;
    }

    // Lock file is created/removed inside bulkConvertWebP()
    bulkConvertWebP();

    $response['success'] = true;
    $response['message'] = 'Bulk WebP conversion completed.';

} elseif ($action === 'get_bulk_webp_status') {
    $lockFile = getCacheDir() . '/webp_conversion.lock';
    $response['success'] = true;
    $response['data'] = [
        'running' => isLockActive($lockFile)
    ];
}
