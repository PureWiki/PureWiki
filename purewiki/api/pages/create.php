<?php
/**
 * PureWiki - Page Creation
 *
 * Handles the creating of new pages and folders.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');
$parentPath = $_POST['parent_path'] ?? '';
$title = $_POST['title'] ?? '';
$layout = $_POST['layout'] ?? 'page';

if (!$title) {
    $response['message'] = 'Title is required.';
    return;
}

// Generate folder name
$folderName = generateSlug($title);

if (!$folderName) $folderName = 'untitled_page';

if (str_starts_with($folderName, '_') && $folderName !== '_virtual') {
    $response['message'] = 'Page or folder names cannot start with an underscore.';
    return;
}

$safeParentPath = sanitizePath($parentPath);
$targetPath = $pagesDir . ($safeParentPath ? '/' . $safeParentPath : '') . '/' . $folderName;

if (file_exists($targetPath)) {
    $response['message'] = 'Page or folder already exists.';
    return;
}

if (createDirectory($targetPath)) {
    $now = date('c');
    $author = $_SESSION['pw_user'] ?? '';
    $pageData = [
        'pagetitle' => $title,
        'DateCreated' => $now,
        'DateModified' => $now,
        'description' => '',
        'Author' => $author,
        'Settings' => [
            'Layout' => basename($layout)
        ]
    ];
    writeJsonFile($targetPath . '/page.draft.json', $pageData);
    invalidateTreeCache();
    invalidateSearchIndex();
    $response['success'] = true;
    $response['message'] = 'Page created successfully.';
    $response['new_path'] = '/' . ltrim($safeParentPath . '/' . $folderName, '/');
} else {
    $response['message'] = 'Failed to create directory.';
}
