<?php
/**
 * PureWiki - Page Renaming
 *
 * Updates page/folder names and paths on the file system. Handles
 * updating any internal references if needed.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');
$path = $_POST['path'] ?? '';
$newName = $_POST['new_name'] ?? '';

if (!$path || $path === '/') {
    $response['message'] = 'Invalid path or cannot rename root.';
    return;
}
if (str_starts_with($path, '/_virtual/')) {
    $response['message'] = 'System pages cannot be renamed.';
    return;
}
if (!$newName) {
    $response['message'] = 'New name is required.';
    return;
}

// Generate folder name
$folderName = generateSlug($newName);

if (!$folderName) {
    $response['message'] = 'Invalid new name digits/characters.';
    return;
}

if (str_starts_with($folderName, '_')) {
    $response['message'] = 'Page or folder names cannot start with an underscore.';
    return;
}

$safePath = sanitizePath($path);
$sourceDir = realpath($pagesDir . '/' . $safePath);

if ($sourceDir && isPathInDir($sourceDir, $pagesDir) && is_dir($sourceDir)) {
    $parentPath = dirname($safePath);
    if ($parentPath === '.') $parentPath = '';

    $targetDir = $pagesDir . ($parentPath ? '/' . $parentPath : '') . '/' . $folderName;

    if (file_exists($targetDir)) {
        $response['message'] = 'Target folder already exists.';
        return;
    }

    if (rename($sourceDir, $targetDir)) {
        // Sync titles in json
        $jsonPath = $targetDir . '/page.json';
        if (file_exists($jsonPath)) {
            $data = readJson($jsonPath, []);
            $data['pagetitle'] = $newName;
            writeJsonFile($jsonPath, $data);
        }

        $draftPath = $targetDir . '/page.draft.json';
        if (file_exists($draftPath)) {
            $data = readJson($draftPath, []);
            $data['pagetitle'] = $newName;
            writeJsonFile($draftPath, $data);
        }

        invalidateTreeCache();
        rebuildNavLinksCache();
        invalidateSearchIndex();
        $response['success'] = true;
        $response['message'] = 'Folder renamed successfully.';
        $response['new_path'] = '/' . ltrim(($parentPath !== '' ? $parentPath . '/' : '') . $folderName, '/');
        clearCache();
    } else {
        $response['message'] = 'Failed to rename directory.';
    }
} else {
    $response['message'] = 'Source folder does not exist or access denied.';
}
