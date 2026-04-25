<?php
/**
 * PureWiki - API Router
 *
 * Backend API router for handling AJAX/Fetch requests from the dashboard.
 * Dispatches requests to specific action files in the api directory.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

define('PUREWIKI', true);

require_once __DIR__ . "/core/exception.php";

header('Content-Type: application/json');

set_exception_handler(function($exception) {
    if (!headers_sent()) {
        header('HTTP/1.1 500 Internal Server Error');
        header('Content-Type: application/json');
    }

    $response = [
        'success' => false,
        'message' => $exception->getMessage(),
    ];

    // Pass exception code if it's a specific API error (usually not 0)
    if ($exception->getCode() !== 0) {
        $response['code'] = $exception->getCode();
    }

    echo json_encode($response);
    exit;
});

set_error_handler(function($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        // This error code is not included in error_reporting
        return;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

require_once __DIR__ . '/version.php';
require_once __DIR__ . '/core/json.php';
require_once __DIR__ . '/core/fs.php';
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/cache.php';
require_once __DIR__ . '/core/tree.php';
require_once __DIR__ . '/core/nav.php';
require_once __DIR__ . '/core/search.php';
require_once __DIR__ . '/core/auth.php';
require_once __DIR__ . '/core/backup.php';
require_once __DIR__ . '/core/media.php';
require_once __DIR__ . '/core/i18n.php';
require_once __DIR__ . '/core/misc.php';
require_once __DIR__ . '/core/mail.php';
require_once __DIR__ . '/core/http.php';
require_once __DIR__ . '/core/extension_loader.php';

ExtensionLoader::boot();

startAuth();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if (!$action) {
    echo json_encode(['success' => false, 'message' => 'No action specified.']);
    exit;
}

$response = ['success' => false, 'message' => ''];
$pagesDir = getPageDir();
if (!file_exists($pagesDir)) {
    createDirectory($pagesDir);
}

$apiRoutes = [
    'create_page' => 'pages/create.php',
    'duplicate_page' => 'pages/duplicate.php',
    'delete_page' => 'pages/delete.php',
    'move_page' => 'pages/move.php',
    'drag_drop_page' => 'pages/move.php',
    'rename_folder' => 'pages/rename.php',
    'list_pages' => 'pages/list.php',

    'list_snippets' => 'snippets/list.php',
    'create_snippet' => 'snippets/create.php',
    'delete_snippet' => 'snippets/delete.php',

    'get_page' => 'content/read.php',
    'save_draft' => 'content/draft.php',
    'delete_draft' => 'content/draft.php',
    'publish_page' => 'content/publish.php',
    'get_page_history' => 'content/history.php',
    'restore_page_version' => 'content/history.php',
    'save_page_settings' => 'content/settings.php',

    'list_media' => 'media/media.php',
    'upload_media' => 'media/media.php',
    'delete_media' => 'media/media.php',
    'rename_media' => 'media/media.php',

    'acquire_lock' => 'locks/locks.php',
    'release_lock' => 'locks/locks.php',
    'refresh_lock' => 'locks/locks.php',

    'get_config' => 'system/config.php',
    'save_config' => 'system/config.php',
    'clear_cache' => 'system/cache.php',
    'list_users' => 'system/users.php',
    'create_user' => 'system/users.php',
    'delete_user' => 'system/users.php',
    'search' => 'system/search.php',
    'logout' => 'system/auth.php',
    'get_system_status' => 'system/status.php',
    'start_bulk_webp' => 'media/bulk_convert.php',
    'get_bulk_webp_status' => 'media/bulk_convert.php',

    'backup_image' => 'media/image_edit.php',
    'restore_image' => 'media/image_edit.php',
    'process_image_edit' => 'media/image_edit.php',

    'list_backups' => 'system/backup.php',
    'start_backup' => 'system/backup.php',
    'get_backup_status' => 'system/backup.php',
    'delete_backup' => 'system/backup.php',
    'download_backup' => 'system/backup.php',

    'check_for_updates' => 'system/update.php',
    'get_update_requirements' => 'system/update.php',
    'download_update' => 'system/update.php',
    'start_pre_update_backup' => 'system/update.php',
    'get_update_backup_status' => 'system/update.php',
    'install_update' => 'system/update.php',
    'cleanup_update' => 'system/update.php',

    'setup_wiki' => 'system/setup.php',

    'get_mail_config' => 'system/mail.php',
    'save_mail_config' => 'system/mail.php',
    'disable_mail' => 'system/mail.php',
    'send_test_mail' => 'system/mail.php',

    'list_trash' => 'trash/list.php',
    'restore_trash_item' => 'trash/restore.php',
    'delete_trash_item' => 'trash/delete.php',
    'empty_trash' => 'trash/empty.php',
];

if (!isset($apiRoutes[$action])) {
    echo json_encode(['success' => false, 'message' => 'Invalid action specified.']);
    exit;
}

// Enforce Authentication and Role-Based Access Control
$publicActions = ['search', 'setup_wiki'];
$readerActions = ['logout'];
$adminActions  = [
    'get_config', 'save_config', 'clear_cache', 'list_users', 'create_user', 'delete_user',
    'list_backups', 'start_backup', 'get_backup_status', 'delete_backup', 'download_backup',
    'get_system_status', 'start_bulk_webp', 'get_bulk_webp_status', 'backup_image',
    'restore_image', 'process_image_edit', 'check_for_updates', 'get_update_requirements',
    'download_update', 'start_pre_update_backup', 'get_update_backup_status',
    'install_update', 'cleanup_update',
    'get_mail_config', 'save_mail_config', 'disable_mail', 'send_test_mail',
    'list_trash', 'restore_trash_item', 'delete_trash_item', 'empty_trash',
];

if (!in_array($action, $publicActions)) {
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    if (in_array($action, $adminActions) && !hasRole('admin')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Admin role required.']);
        exit;
    }

    if (!in_array($action, $adminActions) && !in_array($action, $readerActions) && !hasRole('editor')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Editor role required.']);
        exit;
    }
}

$routeFile = __DIR__ . '/api/' . $apiRoutes[$action];
if (file_exists($routeFile)) {
    require_once $routeFile;
} else {
    $response['message'] = 'Action file not found.';
}

echo json_encode($response);
