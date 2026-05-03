<?php
/**
 * PureWiki - Debug Logging
 *
 * Centralized debug logging utility.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/fs.php';

/** Check if dev debug output is enabled in the global config */
function isDebugMode(): bool {
    static $result = null;
    if ($result === null) {
        $result = !empty(getGlobalConfig()['dev_debug_output']);
    }
    return $result;
}

/**
 * Writes a structured debug entry to the log file
 * Only active when dev_debug_output is enabled in the global config.
 * 
 * Example Usage:
 * 
 * Simple Info Message:
 *    pw_debug('User logged in successfully', null, 'INFO', 'auth');
 * 
 * Warning with Context Array (like from Extension):
 *    pw_debug('Failed to get ext page content', ['timeout' => 5], 'WARN', 'ext:example-extension');
 * 
 * Error Logging:
 *    pw_debug('Get page settings error', ['page' => 'Home'], 'ERROR', 'get_page');
 * 
 * Verbose Debugging (default level):
 *    pw_debug('Calculated layout dimensions', $dims, 'DEBUG', 'renderer');
 *
 * @param string      $message	Description of the event
 * @param mixed       $context Optional context data (array or scalar)
 * @param string      $level   DEBUG , INFO , WARN , ERROR
 * @param string      $source  Caller identifier, such as renderer or ext:my-ext
 */
function pw_debug(string $message, mixed $context = null, string $level = 'DEBUG', string $source = 'core'): void {
    if (!isDebugMode()) return;

    $logDir  = getDebugLogDir();
    $logFile = $logDir . DIRECTORY_SEPARATOR . 'debug.log';

    // Create log directory
    if (!is_dir($logDir)) {
        createDirectory($logDir);
    }

    _pw_debug_rotate($logFile);

    $config   = getGlobalConfig();
    $timezone = date_default_timezone_get() ?: 'UTC';
    $date     = date('Y-m-d H:i:s');
    $levelPad = str_pad(strtoupper($level), 5);
    $srcPad   = str_pad($source, 14);

    $line = "[{$date} {$timezone}] [{$levelPad}] [{$srcPad}] {$message}";

    if ($context !== null) {
        $encoded = is_array($context) || is_object($context)
            ? json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : (string) $context;
        $line .= ' | ' . $encoded;
    }

    file_put_contents($logFile, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}

/**
 * Returns the last $lines entries from the debug log as an array of strings
 *
 * @param int $lines Number of lines
 */
function readDebugLog(int $lines = 200): array {
    $logFile = getDebugLogDir() . DIRECTORY_SEPARATOR . 'debug.log';
    if (!file_exists($logFile)) return [];

    $all = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($all)) return [];

    return array_slice($all, -$lines);
}

/** Clears debug log file */
function clearDebugLog(): bool {
    $logFile = getDebugLogDir() . DIRECTORY_SEPARATOR . 'debug.log';
    if (!file_exists($logFile)) return true;
    return file_put_contents($logFile, '', LOCK_EX) !== false;
}

/**
 * Rotates the log file when it exceeds the configured size
 * Shift existing rotated files down and rename the current log to .1
 */
function _pw_debug_rotate(string $logFile): void {
    if (!file_exists($logFile)) return;

    $config  = getGlobalConfig();
    $maxSize = (int)($config['dev_debug_log_max_size']  ?? 2) * 1024 * 1024;
    $maxFiles = (int)($config['dev_debug_log_max_files'] ?? 3);

    if (filesize($logFile) < $maxSize) return;

    // Shift rotated files: .2 → gone, .1 → .2, current → .1
    for ($i = $maxFiles - 1; $i >= 1; $i--) {
        $old = $logFile . '.' . $i;
        $new = $logFile . '.' . ($i + 1);
        if (file_exists($old)) {
            if ($i + 1 > $maxFiles) {
                @unlink($old);
            } else {
                @rename($old, $new);
            }
        }
    }

    @rename($logFile, $logFile . '.1');
}
