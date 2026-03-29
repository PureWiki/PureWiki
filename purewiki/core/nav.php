<?php
/**
 * PureWiki - Navigation Links
 *
 * Functions for building and caching the navbar links
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

require_once __DIR__ . '/cache.php';

/**
 * Rebuilds the nav links cache using the cached page tree index.
 * Writes the result to cache/navlinks.json.
 */
function rebuildNavLinksCache(): void {
    require_once __DIR__ . '/tree.php';
    $pagesDir = getPageDir();

    // Rely on the pre-built page tree here instead of rescanning
    // the filesystem to avoid an N+1 performance cliff when checking `page.json`.
    $tree = getCachedPagesTree($pagesDir);

    $links = [];
    _extractNavLinksFromTree($tree, $links);

    // Also check the root Startpage, as it's not a node in the tree structure
    $rootJsonPath = $pagesDir . '/page.json';
    if (file_exists($rootJsonPath)) {
        $rootData = readJson($rootJsonPath, null);
        if (is_array($rootData) && !empty($rootData['Settings']['include_in_navbar'])) {
            $links[] = [
                'path'      => '/',
                'title'     => $rootData['pagetitle'] ?? 'Startpage',
                'link_text' => $rootData['Settings']['navbar_link_text'] ?? '',
                'order'     => $rootData['Order'] ?? 0
            ];
        }
    }


    // Priority: Explicitly set order, fallback to alphabetical
    usort($links, function($a, $b) {
        if ($a['order'] === $b['order']) return strcasecmp($a['title'], $b['title']);
        return $a['order'] - $b['order'];
    });

    // The frontend doesn't need 'order' for rendering; drop it to keep the JSON footprint small
    $clean = [];
    foreach ($links as &$l) {
        $clean[] = ['path' => $l['path'], 'title' => $l['title'], 'link_text' => $l['link_text']];
    }
    unset($l);

    $cacheDir = getCacheDir();
    if (!is_dir($cacheDir)) {
        require_once __DIR__ . '/fs.php';
        createDirectory($cacheDir);
    }
    writeJsonFile($cacheDir . '/navlinks.json', $clean);
}

/**
 * Recursively collects pages with include_in_navbar enabled from the cached tree.
 */
function _extractNavLinksFromTree(array $tree, array &$links): void {
    foreach ($tree as $node) {
        if (!empty($node['include_in_navbar'])) {
            $links[] = [
                'path'      => '/' . ltrim($node['path'], '/'),
                'title'     => $node['name'],
                'link_text' => $node['navbar_link_text'] ?? '',
                'order'     => $node['order'] ?? 999
            ];
        }
        if (!empty($node['children'])) {
            _extractNavLinksFromTree($node['children'], $links);
        }
    }
}

/**
 * Returns the nav links array from cache, or rebuilds if missing.
 * @return array List of nav link entries [path, title, link_text].
 */
function getNavLinks(): array {
    $cacheFile = getCacheDir() . '/navlinks.json';

    if (file_exists($cacheFile)) {
        $data = readJson($cacheFile, null);
        if (is_array($data)) return $data;
    }

    // Rebuild and return
    rebuildNavLinksCache();
    if (file_exists($cacheFile)) {
        return readJson($cacheFile, []);
    }
    return [];
}
