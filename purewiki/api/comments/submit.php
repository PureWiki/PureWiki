<?php
/**
 * PureWiki - Submit Comment API Action
 *
 * Handles public comment submissions
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

require_once __DIR__ . '/../../core/comments.php';
require_once __DIR__ . '/../../core/json.php';
require_once __DIR__ . '/../../core/fs.php';

$path    = $_POST['path'] ?? '';
$name    = $_POST['name'] ?? '';
$email   = $_POST['email'] ?? '';
$text    = $_POST['text'] ?? '';
$website = $_POST['website'] ?? ''; // Honeypot to prevent spam bots

// Honeypot check: Silently ignore when website field is filled
if ($website !== '') {
    $response['success'] = true;
    return;
}

// Validate required inputs
if ($path === '' || $name === '' || $email === '' || $text === '') {
    $response['message'] = 'All fields are required.';
    return;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $response['message'] = 'Invalid email address.';
    return;
}

$safePath = sanitizePath($path);
$targetDir = $safePath ? ($pagesDir . '/' . $safePath) : $pagesDir;

// ensure target is within pagesDir
if (!isPathInDir($targetDir, $pagesDir)) {
    $response['message'] = 'Target out of bounds.';
    return;
}

// Check if comments are enabled in wiki settings
$config = getGlobalConfig();
if (empty($config['comments_enabled'])) {
    $response['message'] = 'Comments are globally disabled.';
    return;
}

// Check if comments are enabled for this page
$pageJson = $targetDir . '/page.json';
if (!file_exists($pageJson)) {
    $pageJson = $targetDir . '/page.draft.json';
}

if (!file_exists($pageJson)) {
    $response['message'] = 'Page not found.';
    return;
}

try {
    $pageData = readJsonFile($pageJson);
} catch (PureWikiException $e) {
    $response['message'] = 'Failed to load page settings.';
    return;
}

if (empty($pageData['Settings']['enable_comments'])) {
    $response['message'] = 'Comments are disabled for this page.';
    return;
}

// Save comment
try {
    $newComment = addComment($safePath, $name, $email, $text);
    if ($newComment['status'] === 'approved') {
        require_once __DIR__ . '/../../core/cache.php';
        clearCache($safePath);
    }
    logActivity('comment_add', 'comment', '/' . $safePath, ['author' => $name]);
    $response['success'] = true;
    $response['data'] = [
        'id' => $newComment['id'],
        'status' => $newComment['status']
    ];
} catch (PureWikiException $e) {
    $response['message'] = 'Failed to save comment.';
}