<?php
/**
 * PureWiki - Cache Management
 *
 * Functions for managing frontend page cache, tree cache, and search index cache
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

require_once __DIR__ . '/fs.php';

/**
 * Clears the HTML page cache.
 * If a path is given, only that page's cache file is deleted.
 * If null, the entire cache directory is purged.
 *
 * @param string|null $path The page path to clear, or null for all.
 */
function clearCache(?string $path = null): void {
    $cacheDir = getCacheDir();
    if (!is_dir($cacheDir)) return;

    if ($path !== null) {
        $file = $cacheDir . '/' . md5($path) . '.html';
        if (file_exists($file)) {
            if (!unlink($file)) {
                // Surface I/O errors immediately to avoid serving stale content
                throw new PureWikiException("Failed to delete cache file for page.");
            }
        }
    } else {
        foreach (glob($cacheDir . '/*.html') as $file) {
            if (is_file($file)) {
                if (!unlink($file)) {
                    // Abort immediately because a partial clear can result in serving
                    // a mix of stale and fresh pages, causing bizarre navigation bugs.
                     throw new PureWikiException("Failed to delete cache file.");
                }
            }
        }
        // Purge the temporary updates folder to prevent pending
        // edits or unapplied patches from becoming orphaned artifacts.
        clearUpdateCache();
    }
}

/**
 * Clears the updates directory within the cache.
 */
function clearUpdateCache(): void {
    $updatesDir = getCacheDir() . '/updates';
    if (!is_dir($updatesDir)) return;

    $deleteRecursive = function($dir) use (&$deleteRecursive) {
        if (!is_dir($dir)) return @unlink($dir);
        foreach (scandir($dir) as $item) {
            if ($item == '.' || $item == '..') continue;
            if (!$deleteRecursive($dir . DIRECTORY_SEPARATOR . $item)) return false;
        }
        return @rmdir($dir);
    };

    $deleteRecursive($updatesDir);
}

/**
 * Invalidates the cached page tree file so it will be rebuilt on the next request.
 */
function invalidateTreeCache(): void {
    $cacheFile = getCacheDir() . '/pagetree.json';
    if (file_exists($cacheFile)) {
        if (!unlink($cacheFile)) {
            throw new PureWikiException("Failed to delete pagetree cache file.");
        }
    }
    invalidateSnippetCache();
}

/**
 * Invalidates the cached snippets list.
 */
function invalidateSnippetCache(): void {
    $cacheFile = getCacheDir() . '/snippets.json';
    if (file_exists($cacheFile)) {
        if (!unlink($cacheFile)) {
            throw new PureWikiException("Failed to delete snippets cache file.");
        }
    }
}

/**
 * Invalidates the search index cache file.
 */
function invalidateSearchIndex(): void {
    $cacheFile = getCacheDir() . '/searchindex.json';
    if (file_exists($cacheFile)) {
        unlink($cacheFile);
    }
}
