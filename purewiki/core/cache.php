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
 * @param string|null $lang The language to clear, or null for all languages.
 */
function clearCache(?string $path = null, ?string $lang = null): void {
    $cacheDir = getCacheDir();
    if (!is_dir($cacheDir)) return;

    if ($path !== null) {
        $keys = [];
        if ($lang !== null) {
            $keys[] = $lang ? $lang . ':' . $path : $path;
        } else {
            $keys[] = $path;
            require_once __DIR__ . '/config.php';
            $config = getGlobalConfig();
            if (!empty($config['i18n_enabled']) && !empty($config['i18n_supported_langs'])) {
                foreach ($config['i18n_supported_langs'] as $l) {
                    $keys[] = $l . ':' . $path;
                }
            }
        }

        foreach ($keys as $key) {
            $file = $cacheDir . '/' . md5($key) . '.html';
            if (file_exists($file)) {
                if (!unlink($file)) {
                    throw new PureWikiException("Failed to delete cache file for page.");
                }
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
 * Invalidates the cached page tree file for every language so it will be rebuilt on the next request.
 */
function invalidateTreeCache(): void {
    $cacheDir = getCacheDir();

    // Delete all pagetree cache files
    foreach (glob($cacheDir . '/pagetree*.json') ?: [] as $file) {
        if (is_file($file) && !unlink($file)) {
            throw new PureWikiException("Failed to delete pagetree cache file: " . basename($file));
        }
    }

    // Delete all navlinks cache files
    foreach (glob($cacheDir . '/navlinks*.json') ?: [] as $file) {
        if (is_file($file) && !unlink($file)) {
            throw new PureWikiException("Failed to delete navlinks cache file: " . basename($file));
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
