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
$userDir = realpath($pagesDir . '/' . $safePath);

if ($action === 'get_page_langs') {
    require_once __DIR__ . '/../../core/i18n_pages.php';
    $supported = getSupportedPageLangs();
    $defaultLang = getDefaultPageLang();

    $response['success'] = true;
    $response['default_lang'] = $defaultLang;
    $response['supported_langs'] = $supported;
    $response['available'] = [];

    $langsToCheck = array_merge([''], $supported);
    foreach ($langsToCheck as $l) {
        $draftFile = $userDir . '/' . getPageFilename($l, true);
        $publishFile = $userDir . '/' . getPageFilename($l, false);
        $response['available'][$l] = [
            'has_draft' => $userDir ? file_exists($draftFile) : false,
            'has_published' => $userDir ? file_exists($publishFile) : false
        ];
    }
    return;
}

$isVirtual = str_starts_with($safePath, '_virtual/');
$virtualPagesDir = getVirtualPagesDir();
$systemDir = realpath($virtualPagesDir . '/' . $safePath);

$finalPath = null;
$isDraft = false;
$lang = $_POST['lang'] ?? '';
if (!function_exists('getPageFilename')) {
    require_once __DIR__ . '/../../core/i18n_pages.php';
}

$draftFilename = getPageFilename($lang, true);
$publishFilename = getPageFilename($lang, false);

if ($userDir && file_exists($userDir . '/' . $draftFilename)) {
    $finalPath = $userDir . '/' . $draftFilename;
    $isDraft = true;
} 
elseif ($userDir && file_exists($userDir . '/' . $publishFilename)) {
    $finalPath = $userDir . '/' . $publishFilename;
}
elseif ($isVirtual && $systemDir && file_exists($systemDir . '/page.json')) {
    // Virtual pages are currently not multilingual in this implementation
    $finalPath = $systemDir . '/page.json';
}

if ($finalPath) {
    if (isPathInDir($finalPath, $pagesDir) || ($isVirtual && isPathInDir($finalPath, $virtualPagesDir))) {
        $response['success'] = true;
        $response['data'] = readJsonFile($finalPath) ?: [];
        $response['is_draft'] = $isDraft;

        if (!function_exists('getSupportedPageLangs')) {
            require_once __DIR__ . '/../../core/i18n_pages.php';
        }
        $availLangs = [];
        if ($userDir && is_dir($userDir)) {
            $langsToCheck = array_merge([''], getSupportedPageLangs());
            foreach ($langsToCheck as $l) {
                $draftFile = $userDir . '/' . getPageFilename($l, true);
                $publishFile = $userDir . '/' . getPageFilename($l, false);
                if (file_exists($draftFile) || file_exists($publishFile)) {
                    $availLangs[] = $l;
                }
            }
        }
        $response['available_langs'] = $availLangs;

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
