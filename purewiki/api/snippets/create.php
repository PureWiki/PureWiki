<?php
/**
 * PureWiki - Create Snippet Action
 *
 * Handles the creation of new snippets.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

if ($action === 'create_snippet') {
    $title = $_POST['title'] ?? '';

    if (!$title) {
        $response['message'] = 'Title is required.';
        return;
    }

    $snippetsDir = getSnippetsDir();
    if (!file_exists($snippetsDir)) {
        createDirectory($snippetsDir);
    }

    // Generate folder name
    $folderName = generateSlug($title);

    if (!$folderName) $folderName = 'untitled_snippet';

    $targetPath = $snippetsDir . '/' . $folderName;

    if (file_exists($targetPath)) {
        $response['message'] = 'Snippet already exists.';
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
                'Layout' => 'page'
            ]
        ];
        writeJsonFile($targetPath . '/page.draft.json', $pageData);
        invalidateTreeCache();
        $response['success'] = true;
        $response['message'] = 'Snippet created successfully.';
        $response['new_path'] = '/_snippets/' . $folderName;
    } else {
        $response['message'] = 'Failed to create directory.';
    }
}