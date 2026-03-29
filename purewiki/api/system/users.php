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
    $result = createUser($username, $password, $role);
    if ($result === true) {
        $response['success'] = true;
        $response['message'] = 'User created successfully.';
    } else {
        $response['message'] = $result;
    }

} else if ($action === 'delete_user') {
    $username = trim($_POST['username'] ?? '');
    $result = deleteUser($username);
    if ($result === true) {
        $response['success'] = true;
        $response['message'] = 'User deleted successfully.';
    } else {
        $response['message'] = $result;
    }
}
