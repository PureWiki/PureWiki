<?php
/**
 * PureWiki - List Page Comments API Action
 *
 * Lists all comments for a specific page
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

require_once __DIR__ . '/../../core/comments.php';
require_once __DIR__ . '/../../core/fs.php';

$path = $_POST['path'] ?? $_GET['path'] ?? '';
$safePath = sanitizePath($path);
$targetDir = $safePath ? ($pagesDir . '/' . $safePath) : $pagesDir;

// Make sure target is within pagesDir
if (!isPathInDir($targetDir, $pagesDir)) {
    $response['message'] = 'Target out of bounds.';
    return;
}

try {
    $comments = getComments($safePath);
    $response['success'] = true;
    $response['data'] = $comments;
} catch (PureWikiException $exception) {
    $response['message'] = 'Failed to load comments.';
}
