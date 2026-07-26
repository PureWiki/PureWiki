<?php
/**
 * PureWiki - Moderate Comment API Action
 *
 * Approving, hiding, and deleting comments
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

require_once __DIR__ . '/../../core/comments.php';
require_once __DIR__ . '/../../core/fs.php';
require_once __DIR__ . '/../../core/cache.php';

$path      = $_POST['path'] ?? '';
$commentId = $_POST['comment_id'] ?? '';
$modAction = $_POST['mod_action'] ?? '';

if ($path === '' || $commentId === '' || $modAction === '') {
    $response['message'] = 'Missing parameters.';
    return;
}

$safePath = sanitizePath($path);
$targetDir = $safePath ? ($pagesDir . '/' . $safePath) : $pagesDir;

// Make sure target is within pagesDir
if (!isPathInDir($targetDir, $pagesDir)) {
    $response['message'] = 'Target out of bounds.';
    return;
}

$success = false;

if ($modAction === 'approve') {
    $success = updateCommentStatus($safePath, $commentId, 'approved');
} elseif ($modAction === 'hide') {
    $success = updateCommentStatus($safePath, $commentId, 'hidden');
} elseif ($modAction === 'delete') {
    $success = deleteComment($safePath, $commentId);
} else {
    $response['message'] = 'Invalid action.';
    return;
}

if ($success) {
    try {
        // clear cache
        clearCache($safePath);
    } catch (PureWikiException $exception) {
        if (function_exists('pw_debug')) {
            pw_debug("moderate_comment: Failed to clear cache for '$safePath'", 'api');
        }
    }
    logActivity('comment_' . $modAction, 'comment', '/' . $safePath);
    $response['success'] = true;
} else {
    $response['message'] = 'Failed to execute moderation action.';
}
