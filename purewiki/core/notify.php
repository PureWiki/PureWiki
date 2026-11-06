<?php
/**
 * PureWiki - Notifications
 *
 * Sends notification mails about wiki events
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/mail.php';
require_once __DIR__ . '/http.php';
require_once __DIR__ . '/i18n.php';

/** Returns true if mail is on, a notify address is set and the event group is turned on. */
function isNotificationEnabled(string $group): bool {
    $config = getMailConfig();
    if (empty($config['mail_enable'])) return false;
    if (!filter_var($config['mail_notify_address'] ?? '', FILTER_VALIDATE_EMAIL)) return false;
    return !empty($config['mail_notify_' . $group]);
}

/** Returns the absolute URL of the wiki root without trailing slash. */
function getNotificationBaseUrl(): string {
    if (defined('BASE_PATH')) {
        $base = BASE_PATH;
    } else {
        $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/', 2)), '/');
    }
    return getSiteOrigin() . $base;
}

/** Returns absolute URL of a wiki path */
function getNotificationUrl(string $path): string {
    return getNotificationBaseUrl() . '/' . ltrim($path, '/');
}

/** Returns absolute URL of the editor for a page path. */
function getNotificationEditUrl(string $pagePath): string {
    return getNotificationUrl('/dashboard/edit?path=' . urlencode('/' . ltrim($pagePath, '/')));
}

/** Returns a string in the default lang */
function __n(string $key, ...$args): string {
    $config = getGlobalConfig();
    return _translate(loadLanguage($config['dashboard_language'] ?? 'en'), $key, $args);
}

/**
 * Queues a notification mail. It is sent after the response so the request does not wait for SMTP
 *
 * @param string $group  Event group(mail_notify_<group> setting)
 * @param string $title  Heading and subject
 * @param array  $lines  Content lines as label
 * @param array  $links  Buttons as label
 */
function notifyAdmin(string $group, string $title, array $lines = [], array $links = []): void {
    if (!isNotificationEnabled($group)) return;

    if (!empty($_SESSION['pw_user'])) {
        $lines[__n('notify.user')] = $_SESSION['pw_user'];
    }

    $to = getMailConfig()['mail_notify_address'];
    $wikiName = getGlobalConfig()['wiki_name'] ?? 'PureWiki';
    $subject = '[' . $wikiName . '] ' . $title;
    $body = renderNotificationMail($title, $lines, $links);

    register_shutdown_function(function() use ($to, $subject, $body) {
        if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
        try {
            $result = sendMail($to, $subject, $body);
            if ($result !== true) error_log('PureWiki notification failed: ' . $result);
        } catch (\Throwable $e) {
            error_log('PureWiki notification failed: ' . $e->getMessage());
        }
    });
}

/** Returns URL of wiki logo, favicon or the PureWiki icon. */
function getNotificationLogoUrl(string $baseUrl): string {
    $config = getGlobalConfig();
    $logo = $config['wiki_logo'] ?: ($config['wiki_favicon'] ?: '/purewiki/assets/icons/favicon.svg');
    if (str_starts_with($logo, '/')) return $baseUrl . $logo;
    return $logo;
}

/** Returns body of a notification mail. */
function renderNotificationMail(string $title, array $lines, array $links): string {
    $baseUrl = getNotificationBaseUrl();
    $wikiName = htmlspecialchars(getGlobalConfig()['wiki_name'] ?? 'PureWiki');
    $logoUrl = htmlspecialchars(getNotificationLogoUrl($baseUrl));

    $rows = '';
    foreach ($lines as $label => $value) {
        $rows .= '<tr><td style="padding:6px 12px 6px 0;color:#6b7280;vertical-align:top;white-space:nowrap;">' . htmlspecialchars((string)$label) . '</td>'
            . '<td style="padding:6px 0;color:#111827;white-space:pre-line;">' . htmlspecialchars((string)$value) . '</td></tr>';
    }

    $buttons = '';
    foreach ($links as $label => $url) {
        $buttons .= '<a href="' . htmlspecialchars($url) . '" style="display:inline-block;margin:0 8px 8px 0;padding:10px 16px;background:#2563eb;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:600;">' . htmlspecialchars((string)$label) . '</a>';
    }

    return '<!DOCTYPE html><html><body style="margin:0;padding:24px 12px;background:#f3f4f6;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;font-size:15px;line-height:1.5;">'
        . '<div style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:10px;padding:28px;border:1px solid #e5e7eb;">'
        . '<a href="' . htmlspecialchars($baseUrl . '/') . '" style="display:inline-flex;align-items:center;gap:10px;text-decoration:none;color:#111827;font-weight:600;">'
        . '<img src="' . $logoUrl . '" alt="" height="32" style="height:32px;width:auto;vertical-align:middle;border:0;"> <span style="vertical-align:middle;">' . $wikiName . '</span></a>'
        . '<h1 style="font-size:20px;margin:24px 0 16px;color:#111827;">' . htmlspecialchars($title) . '</h1>'
        . ($rows !== '' ? '<table style="border-collapse:collapse;margin-bottom:20px;">' . $rows . '</table>' : '')
        . $buttons
        . '<p style="margin:24px 0 0;padding-top:16px;border-top:1px solid #e5e7eb;font-size:12px;color:#9ca3af;">' . htmlspecialchars(__n('settings.mail_notify_footer')) . '</p>'
        . '</div></body></html>';
}
