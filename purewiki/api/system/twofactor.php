<?php
/**
 * PureWiki - Two-Factor Authentication Action
 *
 * Sets up and turns off the 2FA login of the current user.
 * Codes follow TOTP (RFC 6238) with SHA-1, 6 digits and 30 seconds.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

require_once __DIR__ . '/../../core/totp.php';

$username = $_SESSION['pw_user'] ?? '';
$user = readUsers()[$username] ?? null;

if ($action === 'start_2fa_setup') {
    if (!empty($user['totp_secret'])) {
        $response['message'] = __('auth.2fa_already_enabled');
        return;
    }

    // Keep the secret until confirmed, so a wrong code needs no new scan
    $_SESSION['pw_2fa_setup_secret'] ??= generateTotpSecret();
    $secret = $_SESSION['pw_2fa_setup_secret'];

    $response['success'] = true;
    $response['secret'] = $secret;
    $response['uri'] = getTotpUri($secret, $username, getGlobalConfig()['wiki_name'] ?? 'PureWiki');

} elseif ($action === 'enable_2fa') {
    $secret = $_SESSION['pw_2fa_setup_secret'] ?? '';
    if ($secret === '' || $user === null) {
        $response['message'] = __('auth.2fa_setup_expired');
        return;
    }

    $code = $_POST['code'] ?? '';
    $step = is_string($code) ? verifyTotpCode($secret, $code) : null;
    if ($step === null) {
        $response['message'] = __('login.error_2fa_invalid');
        return;
    }

    setUserTotpSecret($username, $secret, $step);
    unset($_SESSION['pw_2fa_setup_secret']);
    logActivity('user_2fa_enable', 'system', null, ['user' => $username]);

    $response['success'] = true;
    $response['message'] = __('auth.2fa_enabled');

} elseif ($action === 'disable_2fa') {
    $password = $_POST['password'] ?? '';
    if ($user === null || !is_string($password) || !password_verify($password, $user['password_hash'])) {
        $response['message'] = __('auth.error_current_password_invalid');
        return;
    }

    setUserTotpSecret($username, null);
    logActivity('user_2fa_disable', 'system', null, ['user' => $username]);

    $response['success'] = true;
    $response['message'] = __('auth.2fa_disabled');

} elseif ($action === 'reset_user_2fa') {
    if (!hasRole('admin')) {
        $response['message'] = 'Insufficient permissions.';
        return;
    }

    $target = $_POST['username'] ?? '';
    if (!is_string($target) || !setUserTotpSecret($target, null)) {
        $response['message'] = __('auth.error_user_not_found');
        return;
    }

    logActivity('user_2fa_disable', 'system', null, ['user' => $target, 'reset_by' => $username]);

    $response['success'] = true;
    $response['message'] = __('auth.2fa_disabled');
}
