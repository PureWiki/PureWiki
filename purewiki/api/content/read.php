<?php
/**
 * PureWiki - Content Reader
 *
 * Fetches page data for the editor or frontend. Prioritizes drafts
 * over published versions when in preview or edit mode
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

$path = $_POST['path'] ?? '';
if (!$path) {
    $response['message'] = 'Path is required.';
    return;
}

$safePath = sanitizePath($path);


$isVirtual = str_starts_with($safePath, '_virtual/');
$userDir = realpath($pagesDir . '/' . $safePath);
$virtualPagesDir = getVirtualPagesDir();
$systemDir = realpath($virtualPagesDir . '/' . $safePath);

$finalPath = null;
$isDraft = false;


if ($userDir && file_exists($userDir . '/page.draft.json')) {
    $finalPath = $userDir . '/page.draft.json';
    $isDraft = true;
} 

elseif ($userDir && file_exists($userDir . '/page.json')) {
    $finalPath = $userDir . '/page.json';
}

elseif ($isVirtual && $systemDir && file_exists($systemDir . '/page.json')) {
    $finalPath = $systemDir . '/page.json';
}

if ($finalPath) {
    if (isPathInDir($finalPath, $pagesDir) || ($isVirtual && isPathInDir($finalPath, $virtualPagesDir))) {
        $response['success'] = true;
        $response['data'] = readJsonFile($finalPath) ?: [];
        $response['is_draft'] = $isDraft;

        if ($isDraft) {
            $publishPath = $userDir . '/page.json';
            if ($isVirtual && (!file_exists($publishPath) || !is_dir($userDir))) {
                $publishPath = getPageDir() . '/' . $safePath . '/page.json';
            }
            if (file_exists($publishPath)) {
                $response['published_data'] = readJsonFile($publishPath) ?: [];
            }
        }
    } else {
        $response['message'] = 'Target out of bounds.';
    }
} else {
    $response['message'] = 'Page content not found.';
}
