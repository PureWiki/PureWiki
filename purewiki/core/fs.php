<?php
/**
 * PureWiki - Filesystem Utilities
 *
 * Functions for filesystem operations and formatting
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

/**
 * Recursively deletes a directory and its contents.
 * @param string $dir Path to the directory.
 * @return bool True on success, false on failure.
 */
function deleteDirectory($dir) {
    if (!file_exists($dir)) return true;
    if (!is_dir($dir)) {
        if (!unlink($dir)) {
            throw new PureWikiException("Failed to delete file: " . basename($dir));
        }
        return true;
    }

    foreach (scandir($dir) as $item) {
        if ($item == '.' || $item == '..') continue;
        deleteDirectory($dir . DIRECTORY_SEPARATOR . $item); // Throws if an inner call fails
    }
    if (!rmdir($dir)) {
        throw new PureWikiException("Failed to delete directory: " . basename($dir));
    }
    return true;
}

/**
 * Checks if a directory is truly writable.
 * @param string $dir Path to the directory.
 * @return bool True if writable, false otherwise.
 */
function isTrulyWritable($dir) {
    if (!is_dir($dir)) return false;
    $testFile = $dir . DIRECTORY_SEPARATOR . 'pw_write_test_' . uniqid() . '.tmp';
    $isWritable = @file_put_contents($testFile, 'test') !== false;
    if ($isWritable) @unlink($testFile);
    return $isWritable;
}

/**
 * Checks if a lock file is active. Stale locks older than TTL are deleted.
 * @param string $lockFile Path to the lock file.
 * @param int $ttl Time to live in seconds (default 3600).
 * @return bool True if active, false if not active or stale.
 */
function isLockActive(string $lockFile, int $ttl = 3600): bool {
    if (file_exists($lockFile)) {
        if (time() - filemtime($lockFile) > $ttl) {
            unlink($lockFile);
            return false;
        }
        return true;
    }
    return false;
}

/**
 * Recursively copies a directory and its contents.
 * @param string $src The source directory.
 * @param string $dst The destination directory.
 * @param array $ignoreDirs Array of directory names to ignore.
 * @param array $ignoreFiles Array of file names to ignore.
 * @return bool True if successful, false otherwise.
 */
function copyDirectory(string $src, string $dst, array $ignoreDirs = [], array $ignoreFiles = []): bool {
    if (!is_dir($src)) return false;
    if (!is_dir($dst)) createDirectory($dst);

    $dir = opendir($src);
    if (!$dir) return false;

    $success = true;
    while (($file = readdir($dir)) !== false) {
        if ($file === '.' || $file === '..') continue;

        $srcPath = $src . DIRECTORY_SEPARATOR . $file;
        $dstPath = $dst . DIRECTORY_SEPARATOR . $file;

        if (is_dir($srcPath)) {
            if (in_array($file, $ignoreDirs)) continue;
            if (!copyDirectory($srcPath, $dstPath, $ignoreDirs, $ignoreFiles)) $success = false;
        } else {
            if (in_array($file, $ignoreFiles)) continue;
            if (!copy($srcPath, $dstPath)) $success = false;
        }
    }
    closedir($dir);
    return $success;
}

/**
 * Returns the absolute path to the pages directory.
 * @return string
 */
function getPageDir(): string {
    return realpath(__DIR__ . '/../../pages') ?: __DIR__ . '/../../pages';
}

/**
 * Returns the absolute path to the trash directory.
 * @return string
 */
function getTrashDir(): string {
    return getPageDir() . '/_trash';
}

/**
 * Returns the absolute path to the system-provided virtual pages directory.
 * @return string
 */
function getVirtualPagesDir(): string {
    return realpath(__DIR__ . '/../data/pages') ?: __DIR__ . '/../data/pages';
}

/** Returns the absolute path to the global config directory. */
function getConfigDir(): string {
    return realpath(__DIR__ . '/../../config') ?: __DIR__ . '/../../config';
}

function getSnippetsDir(): string {
    return getPageDir() . '/_snippets';
}

/**
 * Returns the absolute path to the cache directory.
 * @return string
 */
function getCacheDir(): string {
    static $dir = null;
    if ($dir === null) {
        $dir = realpath(__DIR__ . '/../../cache') ?: __DIR__ . '/../../cache';
    }
    return $dir;
}

/**
 * Returns the absolute path to the extensions directory.
 * @return string
 */
function getExtensionsDir(): string {
    return realpath(__DIR__ . '/../../extensions') ?: __DIR__ . '/../../extensions';
}

/** Returns the absolute path to the debug log directory. */
function getDebugLogDir(): string {
    return realpath(__DIR__ . '/../logs') ?: __DIR__ . '/../logs';
}

/**
 * Gets the actual path to the Backups directory.
 * @return string
 */
function getBackupDir() {
    return realpath(__DIR__ . '/../..') . '/backups';
}

/**
 * Checks if a path is securely located within a specific base directory.
 */
function isPathInDir(?string $path, string $baseDir): bool {
    if (!$path || !($realBase = realpath($baseDir))) return false;
    $realBase = rtrim(str_replace('\\', '/', $realBase), '/') . '/';

    while (!file_exists($path) && dirname($path) !== $path) {
        $path = dirname($path);
    }

    if (!($realPath = realpath($path))) return false;
    $normalizedPath = rtrim(str_replace('\\', '/', $realPath), '/') . '/';
    return str_starts_with($normalizedPath, $realBase);
}

/**
 * Sanitizes a file path to prevent directory traversal attacks.
 * Removes occurrences of . and .. while normalizing slashes.
 * @param string $path The path to sanitize.
 * @return string The sanitized path.
 */
function sanitizePath($path) {
    if (empty($path)) return '';
    $path = str_replace('\\', '/', $path);
    $parts = explode('/', $path);
    $safeParts = array_filter($parts, fn($p) => $p !== '' && $p !== '.' && $p !== '..');
    return implode('/', $safeParts);
}

/**
 * Safely creates a directory recursively with consistent permissions.
 * @param string $path Path to the directory.
 * @param int $permissions Octal permissions (default 0755).
 * @return bool True on success.
 * @throws PureWikiException
 */
function createDirectory(string $path, int $permissions = 0755): bool {
    if (is_dir($path)) return true;
    if (!mkdir($path, $permissions, true)) {
        throw new PureWikiException("Failed to create directory: " . basename($path));
    }
    return true;
}

/**
 * Moves a page directory into the trash, appending timestamp to avoid collisions.
 * @param string $sourcePath Absolute path to the page directory.
 * @return string The absolute destination path inside the trash.
 */
function moveToTrash(string $sourcePath): string {
    $trashDir = getTrashDir();
    if (!is_dir($trashDir)) {
        createDirectory($trashDir);
    }

    $slug      = basename($sourcePath);
    $dest      = $trashDir . DIRECTORY_SEPARATOR . $slug . '__' . time();

    if (!rename($sourcePath, $dest)) {
        throw new PureWikiException('Failed to move page to trash: ' . $slug);
    }

    return $dest;
}