<?php
/**
 * PureWiki - Recent Pages Action
 *
 * Returns a list of recently modified pages sorted by modification date.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

if ($action === 'list_recent_pages') {
    $pagesDir = getPageDir();
    $limit = isset($_POST['limit']) ? max(1, min(50, (int)$_POST['limit'])) : 10;
    $results = [];

    if (!function_exists('getPageFilename')) {
        require_once __DIR__ . '/../../core/i18n_pages.php';
    }

    /**
     * Collects page info from a given directory
     */
    $collectPage = function(string $dirPath, string $relPath) use (&$results) {
        $jsonPath = $dirPath . '/page.json';
        $draftPath = $dirPath . '/page.draft.json';

        if (!file_exists($jsonPath) && !file_exists($draftPath)) {
            return;
        }

        $data = file_exists($jsonPath) ? readJson($jsonPath, []) : readJson($draftPath, []);
        if (!is_array($data)) {
            return;
        }

        $title = $data['pagetitle'] ?? ($relPath === '/' ? 'Startpage' : prepareTitle(basename($dirPath)));
        $author = $data['Author'] ?? '';
        $dateModified = $data['DateModified'] ?? null;
        $dateCreated = $data['DateCreated'] ?? null;

        // Fallback to filesystem timestamp if DateModified is missing
        $timestamp = 0;
        if (!empty($dateModified)) {
            $parsed = strtotime($dateModified);
            $timestamp = ($parsed !== false) ? $parsed : 0;
        }
        if ($timestamp === 0) {
            $timestamp = file_exists($jsonPath) ? filemtime($jsonPath) : filemtime($draftPath);
            $dateModified = date('c', $timestamp);
        }

        $results[] = [
            'path'         => $relPath,
            'title'        => $title,
            'author'       => $author,
            'modified'     => $dateModified,
            'timestamp'    => $timestamp,
            'created'      => $dateCreated,
            'is_draft'     => file_exists($draftPath),
            'is_published' => file_exists($jsonPath),
            'is_private'   => !empty($data['isPrivate'])
        ];
    };

    // 1. Root page
    $collectPage($pagesDir, '/');

    // 2. Scan all subdirectories recursively
    if (is_dir($pagesDir)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($pagesDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            if (!$item->isDir()) continue;

            $subDirName = $item->getFilename();
            // Skip system, trash, and snippet directories
            if (str_starts_with($subDirName, '_')) continue;

            $subDirPath = $item->getPathname();
            // Normalize slashes
            $normPagesDir = str_replace('\\', '/', $pagesDir);
            $normSubPath = str_replace('\\', '/', $subDirPath);

            // Calculate relative page path
            $relPath = '/' . ltrim(substr($normSubPath, strlen($normPagesDir)), '/');

            // Skip nested paths inside ignored folders
            if (str_contains($relPath, '/_')) continue;

            $collectPage($subDirPath, $relPath);
        }
    }

    // Sort descending by timestamp
    usort($results, fn($a, $b) => $b['timestamp'] <=> $a['timestamp']);

    // Slice top limit
    $sliced = array_slice($results, 0, $limit);

    $response['success'] = true;
    $response['data'] = $sliced;
}
