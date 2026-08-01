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

    $items = scandir($dir);
    if ($items === false) {
        throw new PureWikiException("Failed to read directory for deletion: " . basename($dir));
    }

    foreach ($items as $item) {
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
            @unlink($lockFile);
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

    $dir = @opendir($src);
    if (!$dir) {
        if (function_exists('pw_debug')) {
            pw_debug("copyDirectory: Failed to open directory '$src'", 'fs');
        }
        return false;
    }

    while (($file = readdir($dir)) !== false) {
        if ($file === '.' || $file === '..') continue;

        $srcPath = $src . DIRECTORY_SEPARATOR . $file;
        $dstPath = $dst . DIRECTORY_SEPARATOR . $file;

        if (is_dir($srcPath)) {
            if (in_array($file, $ignoreDirs)) continue;
            if (!copyDirectory($srcPath, $dstPath, $ignoreDirs, $ignoreFiles)) {
                closedir($dir);
                return false;
            }
        } else {
            if (in_array($file, $ignoreFiles)) continue;
            if (!copy($srcPath, $dstPath)) {
                closedir($dir);
                return false;
            }
        }
    }
    closedir($dir);
    return true;
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

/** 
 * Returns the absolute path to the global config directory. 
 * @return string
*/
function getConfigDir(): string {
    return realpath(__DIR__ . '/../../config') ?: __DIR__ . '/../../config';
}

/**
 * Returns the absolute path to the snippets directory.
 * @return string
 */
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

    $iterations = 0;
    while (!file_exists($path) && dirname($path) !== $path && $iterations < 100) {
        $path = dirname($path);
        $iterations++;
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
    $isAbsolute = str_starts_with($path, '/');
    $parts = explode('/', $path);
    $safeParts = array_filter($parts, fn($p) => $p !== '' && $p !== '.' && $p !== '..');
    $result = implode('/', $safeParts);
    return $isAbsolute ? '/' . $result : $result;
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

/**
 * Recursively calculates the total size of a directory in bytes.
 * @param string $dir Path to directory.
 * @return int Total size in bytes.
 */
function getDirectorySize(string $dir): int {
    $size = 0;
    if (!is_dir($dir)) return 0;

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $size += $file->getSize();
        }
    }

    return $size;
}

/**
 * Evaluates system disk storage and total Wiki installation size.
 * @return array Storage information array.
 */
function getDiskStorageInfo(): array {
    require_once __DIR__ . '/misc.php';

    $wikiRoot = realpath(__DIR__ . '/../..') ?: __DIR__ . '/../..';
    $wikiSizeBytes = getDirectorySize($wikiRoot);

    $diskFreeBytes = @disk_free_space($wikiRoot);
    $diskTotalBytes = @disk_total_space($wikiRoot);

    if ($diskFreeBytes === false || $diskTotalBytes === false || $diskTotalBytes <= 0) {
        $diskFreeBytes = 0;
        $diskTotalBytes = 0;
        $diskUsedBytes = 0;
        $freePercent = 0.0;
        $usedPercent = 0.0;
    } else {
        $diskUsedBytes = max(0, $diskTotalBytes - $diskFreeBytes);
        $freePercent = round(($diskFreeBytes / $diskTotalBytes) * 100, 1);
        $usedPercent = round(($diskUsedBytes / $diskTotalBytes) * 100, 1);
    }

    return [
        'wiki_size_bytes'     => $wikiSizeBytes,
        'wiki_size_formatted' => formatBytes($wikiSizeBytes),
        'disk_free_bytes'     => $diskFreeBytes,
        'disk_free_formatted' => formatBytes($diskFreeBytes),
        'disk_total_bytes'    => $diskTotalBytes,
        'disk_total_formatted'=> formatBytes($diskTotalBytes),
        'disk_used_bytes'     => $diskUsedBytes,
        'disk_used_formatted' => formatBytes($diskUsedBytes),
        'free_percent'        => $freePercent,
        'used_percent'        => $usedPercent,
        'is_low_space'        => $freePercent < 10.0
    ];
}