<?php
/**
 * PureWiki - Trash: List API Action
 *
 * Returns all entries currently in the trash directory.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

hasRole('admin') || ($response['message'] = 'Admin role required.') && exit;

$trashDir = getTrashDir();
$items    = [];

if (!is_dir($trashDir)) {
    $response['success'] = true;
    $response['items']   = [];
    return;
}

foreach (scandir($trashDir) as $entry) {
    if ($entry === '.' || $entry === '..') continue;

    $entryPath = $trashDir . DIRECTORY_SEPARATOR . $entry;
    if (!is_dir($entryPath)) continue;

    // format: original-slug__timestamp
    $parts       = explode('__', $entry, 2);
    $originalSlug = $parts[0];
    $deletedAt   = isset($parts[1]) ? date('c', (int)$parts[1]) : null;

    $title = $originalSlug;
    $jsonPath = $entryPath . DIRECTORY_SEPARATOR . 'page.json';
    if (!file_exists($jsonPath)) {
        $jsonPath = $entryPath . DIRECTORY_SEPARATOR . 'page.draft.json';
    }
    if (file_exists($jsonPath)) {
        $data = readJson($jsonPath, null);
        if (!empty($data['pagetitle'])) {
            $title = $data['pagetitle'];
        }
    }

    // Count sub-page directories inside the trashed folder
    $childCount = 0;
    foreach (scandir($entryPath) as $child) {
        if ($child === '.' || $child === '..') continue;
        if (is_dir($entryPath . DIRECTORY_SEPARATOR . $child)) $childCount++;
    }

    $items[] = [
        'slug'          => $entry,
        'original_slug' => $originalSlug,
        'title'         => $title,
        'deleted_at'    => $deletedAt,
        'children_count' => $childCount,
    ];
}

// Sort by newest first
usort($items, fn($a, $b) => strcmp($b['slug'], $a['slug']));

$response['success'] = true;
$response['items']   = $items;
