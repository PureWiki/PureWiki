<?php
/**
 * PureWiki - Debug Log API
 *
 * Get and clear the debug log over API 
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

if ($action === 'get_debug_log') {
    $lines = isset($_POST['lines']) ? (int) $_POST['lines'] : 200;
    $lines = max(1, min($lines, 1000));

    $response['success'] = true;
    $response['log']     = readDebugLog($lines);

    $logFile = getDebugLogDir() . DIRECTORY_SEPARATOR . 'debug.log';
    $response['size']    = file_exists($logFile) ? filesize($logFile) : 0;
}

if ($action === 'clear_debug_log') {
    $response['success'] = clearDebugLog();
    if (!$response['success']) {
        $response['message'] = 'Failed to clear debug log.';
    }
}
