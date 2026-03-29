<?php
/**
 * PureWiki - Page Duplication
 *
 * Clones existing pages and their content to a new path
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');
$path = $_POST['path'] ?? '';
$title = $_POST['title'] ?? '';

if (!$path || $path === '/') {
    $response['message'] = 'Invalid path or cannot duplicate root.';
    return;
}
if (!$title) {
    $response['message'] = 'Title is required.';
    return;
}

// Generate folder name
$folderName = generateSlug($title);

if (!$folderName) $folderName = 'duplicated_page';

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
        $response['message'] = 'Target page or folder already exists.';
        return;
    }

    // Set up dir copying
    if (createDirectory($targetDir)) {
        $success = copyDirectory($sourceDir, $targetDir, ['_history'], ['page.lock.json']);

        if ($success) {
            $now = date('c');
            $author = $_SESSION['pw_user'] ?? '';

            // Sync json meta
            foreach (['page.json', 'page.draft.json'] as $jsonFile) {
                $jsonPath = $targetDir . '/' . $jsonFile;
                if (file_exists($jsonPath)) {
                    $data = readJson($jsonPath, []);
                    $data['pagetitle'] = $title;
                    $data['DateCreated'] = $now;
                    $data['DateModified'] = $now;
                    $data['Author'] = $author;
                    // Description and blocks are kept
                    writeJsonFile($jsonPath, $data);
                }
            }

            invalidateTreeCache();
            rebuildNavLinksCache();
            invalidateSearchIndex();
            $response['success'] = true;
            $response['message'] = 'Page duplicated successfully.';
            $response['new_path'] = '/' . ltrim(($parentPath !== '' ? $parentPath . '/' : '') . $folderName, '/');
        } else {
            $response['message'] = 'Errors occurred during copying. Source might be partially duplicated.';
        }
    } else {
        $response['message'] = 'Failed to create target directory.';
    }
} else {
    $response['message'] = 'Source page does not exist or access denied.';
}
