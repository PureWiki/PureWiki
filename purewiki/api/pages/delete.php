<?php
/**
 * PureWiki - Page Deletion
 *
 * Removes pages and their associated data from the file system.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

$path = $_POST['path'] ?? '';
if (!$path || $path === '/') {
    $response['message'] = 'Invalid path or cannot delete root.';
    return;
}
if (str_starts_with($path, '/_virtual/')) {
    $response['message'] = 'System pages cannot be deleted.';
    return;
}

$safePath = sanitizePath($path);
$targetPath = realpath($pagesDir . '/' . $safePath);

// Only deleting in Pages Directory
if ($targetPath && isPathInDir($targetPath, $pagesDir)) {
    deleteDirectory($targetPath);
    invalidateTreeCache();
    rebuildNavLinksCache();
    invalidateSearchIndex();
    $response['success'] = true;
    $response['message'] = 'Page deleted successfully.';
    clearCache('/' . $safePath);
} else {
    $response['message'] = 'Page does not exist or target out of bounds.';
}
