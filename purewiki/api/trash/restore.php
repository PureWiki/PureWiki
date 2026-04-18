<?php
/**
 * PureWiki - Trash: Restore API Action
 *
 * Moves a trashed page back into the pages directory.
 * Appends numeric suffix if original slug is already taken.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

hasRole('admin') || ($response['message'] = 'Admin role required.') && exit;

$slug = sanitizePath($_POST['slug'] ?? '');
if (!$slug) {
    $response['message'] = 'Invalid slug.';
    return;
}

$trashDir  = getTrashDir();
$sourcePath = $trashDir . DIRECTORY_SEPARATOR . $slug;

if (!is_dir($sourcePath) || !isPathInDir($sourcePath, $trashDir)) {
    $response['message'] = 'Trash item not found.';
    return;
}

// Derive the original slug (everything before the timestamp)
$parts        = explode('__', $slug, 2);
$originalSlug = $parts[0];

// Find free destination name to prevent overwriting existing pages
$pagesDir   = getPageDir();
$destSlug   = $originalSlug;
$destPath   = $pagesDir . DIRECTORY_SEPARATOR . $destSlug;
$counter    = 1;
while (file_exists($destPath)) {
    $destSlug = $originalSlug . '-' . $counter;
    $destPath = $pagesDir . DIRECTORY_SEPARATOR . $destSlug;
    $counter++;
}

if (!rename($sourcePath, $destPath)) {
    throw new PureWikiException('Failed to restore page from trash.');
}

invalidateTreeCache();
rebuildNavLinksCache();
invalidateSearchIndex();

$response['success']    = true;
$response['new_slug']   = $destSlug;
$response['renamed']    = ($destSlug !== $originalSlug);
$response['message']    = 'Page restored.';
