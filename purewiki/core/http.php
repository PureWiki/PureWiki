<?php
/**
 * PureWiki - HTTP Utilities
 *
 * Shared HTTP/cURL helpers: SSL configuration and remote content fetching.
 * Includes SSRF protection for remote Markdown fetching.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

/**
 * Applies SSL certificate options to a cURL handle.
 *
 * Enables SSL verification and reads the CA bundle path from php.ini
 * (curl.cainfo / openssl.cafile). If no valid CA bundle is found, cURL
 * uses the system default bundle which works on properly configured servers.
 *
 * @param \CurlHandle $ch
 * @return void
 */
function applyCurlSslOptions($ch): void {
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

    $caFile = ini_get('curl.cainfo') ?: ini_get('openssl.cafile');

    // Apply manual CA bundle if explicitly defined in php.ini.
    // Otherwise fallback to system defaults (common on misconfigured hosting).
    if ($caFile && file_exists($caFile)) {
        curl_setopt($ch, CURLOPT_CAINFO, $caFile);
    }
}

/**
 * Checks if a hostname resolves to a private or reserved IP address.
 *
 * @param string $url
 * @return bool True if safe (public IP), false if unsafe (private/local IP).
 */
function isSafeUrlTarget(string $url): bool {
    $host = parse_url($url, PHP_URL_HOST);
    if (!$host) return false;

    // DNS pre-flight check to block non-resolving or malformed domains.
    $ip = gethostbyname($host);

    if ($ip === $host && !filter_var($host, FILTER_VALIDATE_IP)) {
        return false;
    }

    // SSRF protection: block IP addresses pointing to loopback or internal LAN
    // (like cloud metadata endpoints, internal APIs).
    return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
}

/**
 * Fetches content from a given URL with a timeout.
 *
 * Includes SSRF protection: validates scheme, blocks private IPs and disables
 * redirect following to prevent bypass via Location headers.
 *
 * @param string $url
 * @return string|false The response body, or false on failure.
 */
function fetchMarkdownUrl(string $url) {
    // Validate URL scheme
    if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $url)) {
        return false;
    }

    // Prevent fetching local or internal network IPs
    if (!isSafeUrlTarget($url)) {
        error_log("PureWiki Security: Blocked SSRF attempt for URL: $url");
        return false;
    }

    // Fallback if PHP cURL extension is missing
    if (!function_exists('curl_init')) {
        $wrappers = stream_get_wrappers();
        if (in_array('https', $wrappers)) {
            $context = stream_context_create([
                'http' => [
                    'method'          => 'GET',
                    'timeout'         => 5,
                    'header'          => "User-Agent: PureWiki Fetcher/1.0\r\n",
                    // Disable following redirects in fallback to prevent SSRF via redirect
                    'follow_location' => 0
                ],
                'ssl' => [
                    'verify_peer'      => true,
                    'verify_peer_name' => true
                ]
            ]);
            return @file_get_contents($url, false, $context);
        }
        return false;
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // Disable redirects to prevent an attacker returning a public IP on the
    // pre-flight check but redirecting to an internal IP (SSRF bypass).
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);

    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_USERAGENT, 'PureWiki Fetcher/1.0');

    // Force IPv4 resolution. Some SSRF filters strictly check IPv4 blocks.
    // If cURL uses IPv6, it might bypass the local filter_var validation entirely.
    curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);

    applyCurlSslOptions($ch);

    $content = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($content === false) {
        error_log("PureWiki cURL Error ($url): " . curl_error($ch));
    }

    return ($httpCode >= 200 && $httpCode < 300 && $content !== false) ? $content : false;
}
