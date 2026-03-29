<?php
/**
 * PureWiki - Delete Snippet Action
 *
 * Handles the deletion of snippets.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

if ($action === 'delete_snippet') {
    $path = $_POST['path'] ?? '';

    if (!$path) {
        $response['message'] = 'Path is required.';
        return;
    }

    // Verify the path format: /_snippets/folderName
    if (!preg_match('/^\/_snippets\/([a-zA-Z0-9_-]+)$/', $path, $matches)) {
        $response['message'] = 'Invalid snippet path.';
        return;
    }

    $folderName = $matches[1];
    $snippetsDir = getSnippetsDir();

    if (!file_exists($snippetsDir)) {
        $response['message'] = 'Snippets directory not found.';
        return;
    }

    $targetPath = $snippetsDir . DIRECTORY_SEPARATOR . $folderName;

    if (!file_exists($targetPath)) {
        $response['message'] = 'Snippet not found.';
        return;
    }

    // Delete the directory and contents
    if (deleteDirectory($targetPath)) {
        invalidateTreeCache();
        $response['success'] = true;
        $response['message'] = 'Snippet deleted successfully.';
    } else {
        $response['message'] = 'Failed to delete snippet.';
    }
}