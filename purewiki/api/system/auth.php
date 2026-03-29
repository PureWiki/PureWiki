<?php
/**
 * PureWiki - Authentication Action
 *
 * Handles user session termination (logout). Global authentication
 * logic is managed by the central auth helper.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

if ($action === 'logout') {
    logoutUser();
    $response['success'] = true;
    $response['message'] = 'Logged out successfully.';
}
