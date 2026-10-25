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
            // Includes the result pages of a paginated page list
            $hash = md5($key);
            $files = [...(glob($cacheDir . '/' . $hash . '.html') ?: []), ...(glob($cacheDir . '/' . $hash . '.*.html') ?: [])];
            foreach ($files as $file) {
                if (is_file($file) && !unlink($file)) {
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
        invalidateBacklinksCache();
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

    // Navigation and page lists show other pages --> HTML of all pages is outdated
    foreach (glob($cacheDir . '/*.html') ?: [] as $file) {
        if (is_file($file) && !unlink($file)) {
            throw new PureWikiException("Failed to delete cache file: " . basename($file));
        }
    }
    invalidateSnippetCache();
}

/** Returns the HTML cache file for a cache key, result pages get a .p{n} part. */
function getPageCacheFile(string $key, int $resultPage = 1): string {
    return getCacheDir() . '/' . md5($key) . ($resultPage > 1 ? '.p' . $resultPage : '') . '.html';
}

/** Returns the result page requested with ?page=, or 1 if missing or invalid. */
function getRequestedResultPage(): int {
    $page = $_GET['page'] ?? '';
    if (!is_string($page) || !ctype_digit($page)) return 1;
    return max(1, min(10000, (int) $page));
}

/**
 * Reads and updates flags of the current page render.
 * Holds the result page of a paginated page list and the number of result pages.
 *
 * @param array $changes Flags to set: page (int), pages (int)
 * @return array All flags after the update
 */
function pageRenderFlags(array $changes = []): array {
    static $flags = ['page' => 0, 'pages' => 0];
    $flags = array_merge($flags, $changes);
    return $flags;
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

/**
 * Invalidates backlinks index cache file.
 */
if (!function_exists('invalidateBacklinksCache')) {
    function invalidateBacklinksCache(): void {
        $cacheFile = getCacheDir() . '/backlinks.json';
        if (file_exists($cacheFile)) {
            @unlink($cacheFile);
        }
    }
}