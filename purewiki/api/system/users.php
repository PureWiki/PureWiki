<?php
/**
 * PureWiki - User Management Action
 *
 * Handles administrative user actions such as listing, creating,
 * and deleting dashboard users.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

if ($action === 'list_users') {
    $response['success'] = true;
    $response['message'] = 'OK';
    $response['data'] = listUsers();

} else if ($action === 'create_user') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = $_POST['role'] ?? 'admin';
    $displayName = $_POST['display_name'] ?? '';
    if (!is_string($displayName)) $displayName = '';

    $result = createUser($username, $password, $role, $displayName);
    if ($result === true) {
        $response['success'] = true;
        $response['message'] = 'User created successfully.';
        logActivity('user_create', 'system', null, ['created_user' => $username, 'role' => $role]);

        require_once __DIR__ . '/../../core/notify.php';
        notifyAdmin('users', __n('notify.user_created_title'), [
            __n('notify.username') => $username,
            __n('notify.role')     => $role,
        ], [__n('notify.open_settings') => getNotificationUrl('/dashboard/settings')]);
    } else {
        $response['message'] = $result;
    }

} else if ($action === 'delete_user') {
    $username = trim($_POST['username'] ?? '');
    $result = deleteUser($username);
    if ($result === true) {
        $response['success'] = true;
        $response['message'] = 'User deleted successfully.';
        logActivity('user_delete', 'system', null, ['deleted_user' => $username]);

        require_once __DIR__ . '/../../core/notify.php';
        notifyAdmin('users', __n('notify.user_deleted_title'), [
            __n('notify.username') => $username,
        ], [__n('notify.open_settings') => getNotificationUrl('/dashboard/settings')]);
    } else {
        $response['message'] = $result;
    }
} else if ($action === 'change_password') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword     = $_POST['new_password'] ?? '';
    $username        = $_SESSION['pw_user'] ?? '';

    $result = changeUserPassword($username, $currentPassword, $newPassword);
    if ($result === true) {
        $response['success'] = true;
        $response['message'] = function_exists('__') ? __('auth.password_changed') : 'Password changed successfully.';
        logActivity('user_password_change', 'system', null, ['user' => $username]);

        require_once __DIR__ . '/../../core/notify.php';
        notifyAdmin('security', __n('notify.password_changed_title'), [
            __n('notify.username') => $username,
        ]);
    } else {
        $response['message'] = $result;
    }
} else if ($action === 'change_language') {
    $language = trim($_POST['language'] ?? '');
    $username = $_SESSION['pw_user'] ?? '';

    $result = changeUserLanguage($username, $language);
    if ($result === true) {
        $response['success'] = true;
        $response['message'] = function_exists('__') ? __('auth.language_changed') : 'Language changed successfully.';
        logActivity('user_language_change', 'system', null, ['user' => $username, 'language' => $language]);
    } else {
        $response['message'] = $result;
    }
} else if ($action === 'change_display_name') {
    $displayName = $_POST['display_name'] ?? '';
    $username    = $_SESSION['pw_user'] ?? '';

    $result = is_string($displayName) ? changeUserDisplayName($username, $displayName) : 'Invalid display name.';
    if ($result === true) {
        // Display name is shown as page author --> cached pages are outdated
        require_once __DIR__ . '/../../core/cache.php';
        clearCache();
        $response['success'] = true;
        $response['message'] = function_exists('__') ? __('auth.display_name_changed') : 'Display name changed successfully.';
        logActivity('user_display_name_change', 'system', null, ['user' => $username]);
    } else {
        $response['message'] = $result;
    }
} else if ($action === 'set_user_display_name') {
    $target      = $_POST['username'] ?? '';
    $displayName = $_POST['display_name'] ?? '';

    if (!is_string($target) || !is_string($displayName)) {
        $response['message'] = 'Invalid display name.';
        return;
    }

    $result = changeUserDisplayName($target, $displayName);
    if ($result !== true) {
        $response['message'] = $result;
        return;
    }

    // Display name is shown as page author --> cached pages are outdated
    require_once __DIR__ . '/../../core/cache.php';
    clearCache();
    $response['success'] = true;
    $response['message'] = __('auth.display_name_changed');
    logActivity('user_display_name_change', 'system', null, ['user' => $target, 'changed_by' => $_SESSION['pw_user'] ?? '']);

} else if ($action === 'change_theme') {
    $theme    = trim($_POST['theme'] ?? '');
    $username = $_SESSION['pw_user'] ?? '';

    $result = changeUserTheme($username, $theme);
    if ($result === true) {
        $response['success'] = true;
        $response['message'] = function_exists('__') ? __('auth.theme_changed') : 'Theme changed successfully.';
        logActivity('user_theme_change', 'system', null, ['user' => $username, 'theme' => $theme]);
    } else {
        $response['message'] = $result;
    }
}

