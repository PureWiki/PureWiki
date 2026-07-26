<?php
/**
 * PureWiki - Activity Log API Action
 *
 * Handles API actions for fetching and clearing activity log entries.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

require_once __DIR__ . '/../../core/activity.php';

if ($action === 'get_activity_log') {
    if (!hasRole('admin')) {
        $response['message'] = 'Insufficient permissions.';
        return;
    }
    // Get variables for filtering (POST or GET)
    $limit  = isset($_POST['limit']) ? (int)$_POST['limit'] : (isset($_GET['limit']) ? (int)$_GET['limit'] : 50);
    $offset = isset($_POST['offset']) ? (int)$_POST['offset'] : (isset($_GET['offset']) ? (int)$_GET['offset'] : 0);
    $filterAction = !empty($_POST['filter_action']) ? trim($_POST['filter_action']) : (!empty($_GET['filter_action']) ? trim($_GET['filter_action']) : null);
    $filterUser   = !empty($_POST['filter_user']) ? trim($_POST['filter_user']) : (!empty($_GET['filter_user']) ? trim($_GET['filter_user']) : null);

    $data = getActivityLog($limit, $offset, $filterAction, $filterUser);
    $response['success'] = true;
    $response['message'] = 'OK';
    $response['data']    = $data;

} else if ($action === 'clear_activity_log') {
    if (!hasRole('admin')) {
        $response['message'] = 'Insufficient permissions.';
        return;
    }

    clearActivityLog();
    logActivity('clear_log', 'system', null, ['description' => 'Activity log cleared']);
    $response['success'] = true;
    $response['message'] = 'Activity log cleared successfully.';
}
