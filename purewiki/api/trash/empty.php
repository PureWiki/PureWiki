<?php
/**
 * PureWiki - Trash: Empty API Action
 *
 * Permanently deletes every item in the trash directory.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

hasRole('admin') || ($response['message'] = 'Admin role required.') && exit;

$trashDir = getTrashDir();
$deleted  = 0;

if (!is_dir($trashDir)) {
    $response['success'] = true;
    $response['deleted'] = 0;
    $response['message'] = 'Trash is already empty.';
    return;
}

foreach (scandir($trashDir) as $entry) {
    if ($entry === '.' || $entry === '..') continue;

    $entryPath = $trashDir . DIRECTORY_SEPARATOR . $entry;
    if (!is_dir($entryPath)) continue;

    deleteDirectory($entryPath);
    $deleted++;
}

$response['success'] = true;
$response['deleted'] = $deleted;
$response['message'] = 'Trash emptied.';
