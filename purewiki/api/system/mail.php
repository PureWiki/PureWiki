<?php
/**
 * PureWiki - Mail System API
 *
 * Handles configuration and sending test emails.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

if ($action === 'get_mail_config') {
    $response['success'] = true;
    $response['message'] = 'OK';
    $config = getMailConfig();

    // Do not return password, just indicate if set
    if (!empty($config['mail_password'])) {
        $config['mail_password'] = true;
    } else {
        $config['mail_password'] = false;
    }

    $response['data'] = $config;

} else if ($action === 'save_mail_config') {
    $configDataRaw = $_POST['config'] ?? '{}';
    $configData = json_decode($configDataRaw, true);

    if (is_array($configData)) {
        if (saveMailConfig($configData)) {
            $response['success'] = true;
            $response['message'] = __('settings.save_success');
        } else {
            $response['message'] = __('settings.save_failed');
        }
    } else {
        $response['message'] = 'Invalid config data.';
    }

} else if ($action === 'disable_mail') {
    if (disableAndClearMail()) {
        $response['success'] = true;
        $response['message'] = __('settings.mail_disabled_success');
    } else {
        $response['message'] = __('settings.save_failed');
    }

} else if ($action === 'send_test_mail') {
    $email = trim($_POST['email'] ?? '');

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response['message'] = __('settings.test_mail_missing');
    } else {
        $subject = 'PureWiki - Test Email';
        $body = '<p>Hello,</p><p>This is a test email sent from your PureWiki installation. If you are reading this, your SMTP configuration is correct!</p>';

        $result = sendMail($email, $subject, $body);

        if ($result === true) {
            $response['success'] = true;
            $response['message'] = __('settings.test_mail_sent');
        } else {
            $response['message'] = $result; // Error message from PHPMailer
        }
    }
}
