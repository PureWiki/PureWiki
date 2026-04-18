<?php
/**
 * PureWiki - Trash: Delete Item API Action
 *
 * Permanently deletes a single item from the trash directory.
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

$trashDir   = getTrashDir();
$targetPath = realpath($trashDir . DIRECTORY_SEPARATOR . $slug);

if (!$targetPath || !isPathInDir($targetPath, $trashDir)) {
    $response['message'] = 'Trash item not found or target out of bounds.';
    return;
}

deleteDirectory($targetPath);

$response['success'] = true;
$response['message'] = 'Item permanently deleted.';
