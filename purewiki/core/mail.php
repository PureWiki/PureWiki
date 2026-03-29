<?php
/**
 * PureWiki - Mail Core
 *
 * Handles mail configuration, password encryption, and sending emails via PHPMailer.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

require_once __DIR__ . '/json.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/fs.php';
require_once __DIR__ . '/../extern/PHPMailer/Exception.php';
require_once __DIR__ . '/../extern/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../extern/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

function getMailConfigPath(): string {
    return getConfigDir() . '/mail.json';
}

/**
 * Gets the encryption key from global config. Generates one if it doesnt exist.
 */
function getMailEncryptionKey(): string {
    $config = getGlobalConfig();
    if (empty($config['mail_encryption_key'])) {
        $key = bin2hex(random_bytes(32));
        $config['mail_encryption_key'] = $key;
        saveGlobalConfig(['mail_encryption_key' => $key]);
        return $key;
    }
    return $config['mail_encryption_key'];
}

/** Encrypts a string using AES-256-CBC. */
function encryptMailPassword(string $password): string {
    if (empty($password)) return '';
    $key = substr(getMailEncryptionKey(), 0, 32); // Ensure exactly 32 bytes for AES-256
    $iv = random_bytes(openssl_cipher_iv_length('aes-256-cbc'));
    $encrypted = openssl_encrypt($password, 'aes-256-cbc', $key, 0, $iv);
    return base64_encode($iv . $encrypted);
}

/** Decrypts a string using AES-256-CBC. */
function decryptMailPassword(string $encryptedData): string {
    if (empty($encryptedData)) return '';
    $key = substr(getMailEncryptionKey(), 0, 32);
    $data = base64_decode($encryptedData);
    $ivLength = openssl_cipher_iv_length('aes-256-cbc');
    $iv = substr($data, 0, $ivLength);
    $encrypted = substr($data, $ivLength);
    $decrypted = openssl_decrypt($encrypted, 'aes-256-cbc', $key, 0, $iv);
    return $decrypted !== false ? $decrypted : '';
}

/** Reads from mail.json. */
function getMailConfig(): array {
    $defaultConfig = [
        'mail_enable' => false,
        'mail_host' => '',
        'mail_port' => 587,
        'mail_username' => '',
        'mail_password' => '',
        'mail_encryption' => 'tls',
        'mail_from_address' => '',
        'mail_from_name' => ''
    ];

    try {
        $data = readJsonFile(getMailConfigPath());
        if (is_array($data)) {
            $config = array_merge($defaultConfig, $data);
            return $config;
        }
    } catch (\Exception $e) {
        // Ignored
    }

    return $defaultConfig;
}

/** Saves mail configuration to mail.json, encrypting password */
function saveMailConfig(array $data): bool {
    $currentConfig = getMailConfig();
    $merged = array_merge($currentConfig, $data);

    if (!empty($data['mail_password']) && $data['mail_password'] !== '********') {
        $merged['mail_password'] = encryptMailPassword($data['mail_password']);
    } else {
        // Keep existing password if not updated
        $merged['mail_password'] = $currentConfig['mail_password'];
    }

    return writeJsonFile(getMailConfigPath(), $merged);
}

/**
 * Sends an email using PHPMailer and the configured SMTP settings.
 *
 * @param string $to Email address of the recipient
 * @param string $subject Subject of the email
 * @param string $body Body of the email (HTML supported)
 * @return bool|string True on success, error message on failure
 */
function sendMail(string $to, string $subject, string $body): bool|string {
    $config = getMailConfig();

    if (empty($config['mail_enable'])) {
        return 'Mail functionality is disabled in settings.';
    }

    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = $config['mail_host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $config['mail_username'];
        $mail->Password   = decryptMailPassword($config['mail_password']);

        // Prevent PHP network functions from hanging
        $originalSocketTimeout = ini_get('default_socket_timeout');
        ini_set('default_socket_timeout', 5);

        $mail->Timeout = 5;
        $mail->SMTPKeepAlive = false;

        //Hack: Correct common misconfigurations that cause connections to hang
        if ((int)$config['mail_port'] === 465 && $config['mail_encryption'] === 'tls') {
            $config['mail_encryption'] = 'ssl'; // Port 465 requires SMTPS, not STARTTLS
        } elseif ((int)$config['mail_port'] === 587 && $config['mail_encryption'] === 'ssl') {
            $config['mail_encryption'] = 'tls'; // Port 587 requires STARTTLS, not SMTPS
        }

        if ($config['mail_encryption'] === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ($config['mail_encryption'] === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mail->SMTPSecure = '';
            $mail->SMTPAutoTLS = false;
        }

        $mail->Port       = (int)$config['mail_port'];

        $contextOptions = [
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ]
        ];
        // Only set cafile if php.ini hasnt defined it, like in core/http.php
        if (empty(ini_get('curl.cainfo')) && file_exists(__DIR__ . '/../extern/cacert.pem')) {
             $contextOptions['ssl']['cafile'] = realpath(__DIR__ . '/../extern/cacert.pem');
        }
        $mail->SMTPOptions = $contextOptions;

        $mail->CharSet = 'UTF-8';

        // Recipients
        $mail->setFrom($config['mail_from_address'], $config['mail_from_name']);
        $mail->addAddress($to);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = strip_tags($body);

        $mail->send();

        ini_set('default_socket_timeout', $originalSocketTimeout); // restore
        return true;
    } catch (PHPMailerException $e) {
        if (isset($originalSocketTimeout)) ini_set('default_socket_timeout', $originalSocketTimeout);
        return "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
    } catch (\Exception $e) {
        if (isset($originalSocketTimeout)) ini_set('default_socket_timeout', $originalSocketTimeout);
        return "An error occurred: {$e->getMessage()}";
    }
}

/** Disables mail and clears the configuration completely. */
function disableAndClearMail(): bool {
    // Delete mail.json
    $mailFile = getMailConfigPath();
    if (file_exists($mailFile)) {
        unlink($mailFile);
    }

    // Remove encryption key from global config
    $config = getGlobalConfig();
    if (isset($config['mail_encryption_key'])) {
        unset($config['mail_encryption_key']);
        // Need to explicitly rewrite the full config because saveGlobalConfig uses array_merge
        // which won't remove keys that are completely unset.
        // Quick workaround: Overwrite the key with empty string, then save.
        saveGlobalConfig(['mail_encryption_key' => '']);
    }

    return true;
}
