<?php
/**
 * PureWiki - PHP Built-in Server Router
 *
 * Replicates the .htaccess rewrite rules for local development.
 * Usage: php -S [IP_ADDRESS] router.php
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$root = str_replace('\\', '/', __DIR__);
$localPath = $root . $uri;

// If the URI points to a real FILE (not a directory), check whether it's allowed
if ($uri !== '/' && is_file($localPath)) {
    // Block access to sensitive paths
    $blocked = ['.dev/', 'config/', 'cache/', 'backups/', 'purewiki/admin/', 'purewiki/core/',
                'purewiki/api/', 'purewiki/lang/', 'purewiki/data/', 'purewiki/extern/',
                'purewiki/frontend/', 'purewiki/logs/'];
    foreach ($blocked as $b) {
        if (str_contains(ltrim($uri, '/'), $b)) {
            http_response_code(403);
            echo '403 Forbidden';
            return true;
        }
    }

    // Block raw file extensions (.json, .md, .log, etc.)
    if (preg_match('/\.(md|json|log|sh|ini|env|bak|swp)$/i', $uri)) {
        http_response_code(403);
        echo '403 Forbidden';
        return true;
    }

    return false; // Serve the real file as-is
}

$_SERVER['SCRIPT_NAME'] = '/index.php';

require __DIR__ . '/index.php';