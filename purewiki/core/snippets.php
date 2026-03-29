<?php
/**
 * PureWiki - Snippet Utilities
 *
 * Functions for listing and caching snippets.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

require_once __DIR__ . '/cache.php';
require_once __DIR__ . '/json.php';
require_once __DIR__ . '/fs.php';

/**
 * Returns the list of snippets, using cache if available.
 *
 * @param string $snippetsDir The snippets directory path.
 * @return array The list of snippets.
 */
function getSnippetsList($snippetsDir) {
    $cacheFile = getCacheDir() . '/snippets.json';
    $snippets = null;

    if (file_exists($cacheFile)) {
        try {
            $snippets = readJsonFile($cacheFile);
        } catch (PureWikiException $e) {
            $snippets = null;
        }
    }

    if ($snippets === null) {
        $snippets = [];
        if ($snippetsDir && is_dir($snippetsDir)) {
            $items = scandir($snippetsDir);
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') continue;
                $path = $snippetsDir . DIRECTORY_SEPARATOR . $item;
                if (is_dir($path)) {
                    $title = $item;
                    $jsonPath = $path . DIRECTORY_SEPARATOR . 'page.json';
                    if (file_exists($jsonPath)) {
                        try {
                            $data = readJsonFile($jsonPath);
                            if (is_array($data) && !empty($data['pagetitle'])) {
                                $title = $data['pagetitle'];
                            }
                        } catch (PureWikiException $e) {
                            // If page.json is missing or invalid, we fallback to folder name
                        }
                    }
                    $snippets[] = [
                        'path' => '/_snippets/' . $item,
                        'name' => $title,
                        'folder' => $item
                    ];
                }
            }
            usort($snippets, fn($a, $b) => strcasecmp($a['name'], $b['name']));
        }

        // Write to cache
        try {
            writeJsonFile($cacheFile, $snippets);
        } catch (PureWikiException $e) {
            // Silently fail if cache cannot be written
        }
    }

    return $snippets;
}
