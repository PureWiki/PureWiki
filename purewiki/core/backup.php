<?php
/**
 * PureWiki - Backup Management
 *
 * Provides functionality for creating, listing, and managing backups.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

require_once __DIR__ . '/exception.php';
require_once __DIR__ . '/fs.php';
require_once __DIR__ . '/misc.php';

/**
 * Ensures the backup directory exists. Creates it if it doesn't.
 */
function ensureBackupDirectoryExists() {
    $backupDir = getBackupDir();
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
    }
}

/**
 * Checks if a backup is currently running.
 * @return bool
 */
function isBackupRunning() {
    $lockFile = getBackupDir() . '/.backup.lock';
    return isLockActive($lockFile);
}

/**
 * Returns a list of available backups.
 *
 * @return array
 */
function getBackups() {
    $backupDir = getBackupDir();
    if (!is_dir($backupDir)) {
        return []; // Return empty if not created yet
    }

    $backups = [];
    $files = glob($backupDir . '/*.tar');

    if ($files) {
        foreach ($files as $file) {
            $backups[] = [
                'file' => basename($file),
                'size' => formatBytes(filesize($file)),
                'date' => date('Y-m-d H:i:s', filemtime($file)),
                'timestamp' => filemtime($file)
            ];
        }
    }

    // Sort descending by timestamp
    usort($backups, function($a, $b) {
        return $b['timestamp'] - $a['timestamp'];
    });

    return $backups;
}

/**
 * Deletes a specific backup by filename.
 *
 * @param string $filename
 * @return bool
 */
function deleteBackup($filename) {
    $filepath = getBackupPath($filename);

    if ($filepath && is_file($filepath)) {
        return unlink($filepath);
    }

    return false;
}

/**
 * Gets the full path to a backup file if it exists, otherwise false.
 * Prevents path traversal security vulnerabilities.
 *
 * @param string $filename
 * @return string|false
 */
function getBackupPath($filename) {
    $filename = basename($filename);
    $backupDir = getBackupDir();

    $realBackupDir = realpath($backupDir);
    if (!$realBackupDir) {
        return false;
    }

    $filepath = $backupDir . DIRECTORY_SEPARATOR . $filename;
    $realFilePath = realpath($filepath);

    // Prevent directory traversal attacks by ensuring the resolved
    // path remains strictly within the backup directory.
    if ($realFilePath &&
        str_starts_with($realFilePath, $realBackupDir . DIRECTORY_SEPARATOR) &&
        is_file($realFilePath) &&
        pathinfo($realFilePath, PATHINFO_EXTENSION) === 'tar') {
        return $realFilePath;
    }

    return false;
}

/**
 * Creates a new backup of the wiki.
 *
 * @param string $prefix Optional prefix for the backup filename
 * @return string Filename of created backup
 * @throws PureWikiException If backup could not be created
 */
function createBackup($prefix = 'purewiki_backup') {
    ensureBackupDirectoryExists();
    $backupDir = getBackupDir();
    $lockFile = $backupDir . '/.backup.lock';

    if (isBackupRunning()) {
        throw new PureWikiException('A backup is already in progress.');
    }

    // Timeout would end the request before the lock file is removed
    if (function_exists('set_time_limit')) {
        @set_time_limit(0);
    }
    ignore_user_abort(true);

    if (@file_put_contents($lockFile, time()) === false) {
        throw new PureWikiException('Backup failed: backups folder is not writable.');
    }

    $timestamp = date('Y-m-d_H-i-s');
    $tarFile = $backupDir . '/' . $prefix . '_' . $timestamp . '.tar';
    $rootDir = realpath(__DIR__ . '/../..');

    // Phar checks every dotted part of the path for a archive, which hits open_basedir above web root
    $previousHandler = set_error_handler(function ($severity, $message, $file, $line) use (&$previousHandler) {
        if (str_contains($message, 'open_basedir restriction in effect')) {
            return true;
        }
        return $previousHandler ? $previousHandler($severity, $message, $file, $line) : false;
    });

    try {
        if (file_exists($tarFile)) {
            unlink($tarFile);
        }
        $phar = new PharData($tarFile);

        // Folders and files to exclude from the backup
        $excludes = ['backups', 'cache'];

        $dirIterator = new RecursiveDirectoryIterator($rootDir, RecursiveDirectoryIterator::SKIP_DOTS);

        $filterIterator = new RecursiveCallbackFilterIterator($dirIterator, function ($current, $key, $iterator) use ($excludes, $rootDir, $tarFile) {
            $filename = $current->getFilename();
            $path = sanitizePath($current->getRealPath());
            $normalizedTarFile = sanitizePath($tarFile);

            // Ignore hidden directories (like .git, .jules) and files.
            // Backup needs to remain portable without bloating.
            if (strpos($filename, '.') === 0) {
                return false;
            }
            if ($current->isDir()) {
                foreach ($excludes as $exclude) {
                    // Skip volatile and heavy cache folders entirely.
                    if (strcasecmp($filename, $exclude) === 0) {
                        return false;
                    }
                }
            } else {
                // Do not pack the tar file we are currently creating.
                if ($path === $normalizedTarFile) {
                    return false;
                }
            }
            return true;
        });

        $iterator = new RecursiveIteratorIterator($filterIterator, RecursiveIteratorIterator::LEAVES_ONLY);
        $phar->buildFromIterator($iterator, $rootDir);

        unset($phar);

        clearstatcache(true, $tarFile);
        if (!is_file($tarFile) || filesize($tarFile) === 0) {
            throw new RuntimeException('Archive was not written.');
        }

        return basename($tarFile);
    } catch (Throwable $e) {
        // Remove not finished archive so it does not show up as valid backup
        unset($phar);
        if (is_file($tarFile)) {
            @unlink($tarFile);
        }

        error_log('PureWiki: Backup failed: ' . $e->getMessage());
        throw new PureWikiException('Backup failed: ' . $e->getMessage(), 0, $e);
    } finally {
        restore_error_handler();
        if (file_exists($lockFile)) {
            @unlink($lockFile);
        }
    }
}
