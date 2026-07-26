<?php
/**
 * PureWiki - Activity Logging
 *
 * Provides functions for logging actions within PureWiki.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

require_once __DIR__ . '/json.php';
require_once __DIR__ . '/fs.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

/** Returns absolute file path to activity_log.json */
function getActivityLogFilePath(): string {
    return getConfigDir() . '/activity_log.json';
}

/** Checks if activity logging is enabled in global config */
function isActivityLogEnabled(): bool {
    $config = getGlobalConfig();
    return !empty($config['enable_activity_log']);
}

/**
 * Appends a new activity to the activity log
 *
 * @param string      $action     Action identifier (like page_publish or user_login)
 * @param string      $targetType Entity category (like page, media, comment, auth, system)
 * @param string|null $targetPath Optional path or identifier
 * @param array       $details    Optional metadata key-value pairs
 * @return bool
 */
function logActivity(string $action, string $targetType = 'system', ?string $targetPath = null, array $details = []): bool {
    if (!isActivityLogEnabled()) {
        return false;
    }

    if (function_exists('startAuth')) startAuth();
    $user = $_SESSION['pw_user'] ?? 'system';
    $role = $_SESSION['pw_role'] ?? 'guest';

    if ($targetPath !== null && $targetPath !== '') {
        $targetPath = '/' . ltrim($targetPath, '/');
    }

    $entry = [
        'id'          => 'act_' . uniqid(),
        'timestamp'   => date('c'),
        'user'        => $user,
        'user_role'   => $role,
        'action'      => $action,
        'target_type' => $targetType,
        'target_path' => $targetPath,
        'details'     => $details
    ];

    $filePath = getActivityLogFilePath();
    $dir = dirname($filePath);
    if (!file_exists($dir)) {
        createDirectory($dir);
    }

    try {
        $log = readJsonFile($filePath);
        if (!is_array($log)) $log = [];
    } catch (PureWikiException $e) {
        $log = [];
    }

    array_unshift($log, $entry);

    $config = getGlobalConfig();
    $maxEntries = (int)($config['activity_log_max_entries'] ?? 1000);
    if ($maxEntries > 0 && count($log) > $maxEntries) {
        $log = array_slice($log, 0, $maxEntries);
    }

    writeJsonFile($filePath, $log);
    return true;
}

/**
 * Get filtered activity log entries
 *
 * @param int         $limit        Max items (defaults 50)
 * @param int         $offset       Starting index
 * @param string|null $filterAction Optional action or target_type filter
 * @param string|null $filterUser   Optional username filter
 * @return array Hash with "total" and "items"
 */
function getActivityLog(int $limit = 50, int $offset = 0, ?string $filterAction = null, ?string $filterUser = null): array {
    $filePath = getActivityLogFilePath();
    try {
        $log = readJsonFile($filePath);
        if (!is_array($log)) $log = [];
    } catch (PureWikiException $e) {
        $log = [];
    }

    if ($filterAction !== null && $filterAction !== '' && $filterAction !== 'all') {
        $log = array_values(array_filter($log, function($item) use ($filterAction) {
            return ($item['action'] ?? '') === $filterAction || ($item['target_type'] ?? '') === $filterAction;
        }));
    }

    if ($filterUser !== null && $filterUser !== '') {
        $userQuery = mb_strtolower(trim($filterUser));
        $log = array_values(array_filter($log, function($item) use ($userQuery) {
            $u = mb_strtolower($item['user'] ?? '');
            $p = mb_strtolower($item['target_path'] ?? '');
            return str_contains($u, $userQuery) || str_contains($p, $userQuery);
        }));
    }

    $total = count($log);
    $items = array_slice($log, $offset, $limit);

    return [
        'total' => $total,
        'items' => $items
    ];
}

/** Clears activity log */
function clearActivityLog(): bool {
    $filePath = getActivityLogFilePath();
    $dir = dirname($filePath);
    if (!file_exists($dir)) {
        createDirectory($dir);
    }
    return writeJsonFile($filePath, []);
}
