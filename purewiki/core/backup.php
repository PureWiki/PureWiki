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
 * @return bool
 */
function createBackup($prefix = 'purewiki_backup') {
    $backupDir = getBackupDir();
    $lockFile = $backupDir . '/.backup.lock';

    if (isBackupRunning()) {
        return false;
    }

    // Create lock file
    file_put_contents($lockFile, time());

    try {
        $timestamp = date('Y-m-d_H-i-s');
        $tarFile = $backupDir . '/' . $prefix . '_' . $timestamp . '.tar';
        $rootDir = realpath(__DIR__ . '/../..');

        try {
            if (file_exists($tarFile)) {
                unlink($tarFile);
            }
            $phar = new PharData($tarFile);
        } catch (Exception $e) {
            error_log("BackupHelper: Could not open tar file for writing: " . $e->getMessage());
            return false;
        }

        require_once __DIR__ . '/fs.php';

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

        return true;
    } catch (Exception $e) {
        error_log("BackupHelper: Critical error during backup: " . $e->getMessage());
        return false;
    } finally {
        if (file_exists($lockFile)) {
            unlink($lockFile);
        }
    }
}
