<?php
/**
 * PureWiki - Page Settings
 *
 * Manages metadata and configuration for specific pages
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

require_once __DIR__ . '/../../core/pagesettings.php';

$path    = $_POST['path'] ?? '';
$safePath = sanitizePath($path);
$isVirtual = str_starts_with($safePath, '_virtual/');

$targetDir = $safePath ? ($pagesDir . '/' . $safePath) : $pagesDir;

// Security check: ensure target is within pagesDir
if (!isPathInDir($targetDir, $pagesDir)) {
    $response['message'] = 'Target out of bounds.';
    return;
}

if ($targetDir) {
    $publishPath = $targetDir . '/page.json';
    $draftPath   = $targetDir . '/page.draft.json';

    if (!is_dir($targetDir)) {
        createDirectory($targetDir);
    }

    $hasPublish = file_exists($publishPath);
    $hasDraft   = file_exists($draftPath);

    $publishSaved = true;
    $draftSaved   = true;
    $base         = [];

    // Apply to published file if exists
    if ($hasPublish || !$hasDraft) {
        $base = readJson($publishPath, []);
        $base = applyPageSettings($base, $_POST);
        $publishSaved  = writeJsonFile($publishPath, $base);
    }

    // Apply to draft file if exists
    if ($hasDraft) {
        $draftContent = readJson($draftPath, $base ?? []);
        $draftContent = applyPageSettings($draftContent, $_POST);
        $draftSaved = writeJsonFile($draftPath, $draftContent);
    }


    // Changes to page metadata
    // invalidate all structural caches immediately.
    if ($publishSaved && $draftSaved) {
        invalidateTreeCache();
        rebuildNavLinksCache();
        clearCache();
        $response['success'] = true;
        $response['message'] = 'Page settings saved.';
    } else {
        $response['message'] = 'Failed to write one or more files.';
    }
} else {
    $response['message'] = 'Invalid path.';
}
