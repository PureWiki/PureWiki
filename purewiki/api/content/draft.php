<?php
/**
 * PureWiki - Content Saver (Drafts)
 *
 * Persists editor changes to the file system as drafts. Handles block-based
 * content from Editor.js and updates page metadata
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

require_once __DIR__ . '/../../core/i18n_pages.php';

if ($action === 'save_draft') {
    $path = $_POST['path'] ?? '';
    $title = $_POST['title'] ?? null;
    $blocksRaw = $_POST['blocks'] ?? '[]';

    if (!$path) {
        $response['message'] = 'Path is required.';
        return;
    }

    $blocks = json_decode($blocksRaw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $response['message'] = 'Invalid JSON blocks data.';
        return;
    }

    // Clean path securely
    $safePath = sanitizePath($path);
    $isVirtual = str_starts_with($safePath, '_virtual/');

    $targetDir = ($safePath && $safePath !== '.') ? ($pagesDir . '/' . $safePath) : $pagesDir;

    // Security: Ensure target is within pagesDir
    if (!isPathInDir($targetDir, $pagesDir)) {
        $response['message'] = 'Target out of bounds.';
        return;
    }

    if ($targetDir) {
        $lang = $_POST['lang'] ?? '';
        $draftPath = $targetDir . '/' . getPageFilename($lang, true);
        $publishPath = $targetDir . '/' . getPageFilename($lang, false);

        $pageData = ['pagetitle' => '', 'blocks' => []];
        
        if (!file_exists($draftPath) && !file_exists($publishPath) && $lang !== '') {
            $defaultPublish = $targetDir . '/page.json';
            $defaultDraft   = $targetDir . '/page.draft.json';
            $source = file_exists($defaultPublish) ? $defaultPublish
                    : (file_exists($defaultDraft) ? $defaultDraft : null);
            if ($source) {
                $sourceData = readJson($source, []);
                $pageData = array_merge($pageData, $sourceData);
            }
        }
        
        if (file_exists($draftPath)) {
            $existingData = readJson($draftPath, null);
            if (is_array($existingData)) {
                $pageData = array_merge($pageData, $existingData);
            }
        } elseif (file_exists($publishPath)) {
            $existingData = readJson($publishPath, null);
            if (is_array($existingData)) {
                $pageData = array_merge($pageData, $existingData);
            }
        } elseif ($isVirtual) {
            // Fallback to system data for virtual pages base data
            $systemPath = getPageDir() . '/' . $safePath . '/page.json';
            if (file_exists($systemPath)) {
                $existingData = readJson($systemPath, null);
                if (is_array($existingData)) {
                    $pageData = array_merge($pageData, $existingData);
                }
            }
        }


        if ($title !== null) {
            $pageData['pagetitle'] = $title;
        }

        if (empty($pageData['DateCreated'])) {
            $pageData['DateCreated'] = date('c');
        }

        // Set Author from session if not already set (Creator)
        if (empty($pageData['Author'])) {
            $pageData['Author'] = $_SESSION['pw_user'] ?? '';
        }

        // Track LastEditor for history
        $pageData['LastEditor'] = $_SESSION['pw_user'] ?? '';

        // API expects modified timestamp bump
        $pageData['DateModified'] = date('c');

        $pageData['blocks'] = $blocks;

        // Explicitly create the directory here.
        // Target may not exist yet for virtual pages or fresh paths.
        if (!is_dir($targetDir)) {
            createDirectory($targetDir);
        }

        writeJsonFile($draftPath, $pageData);
        $response['success'] = true;
        $response['message'] = 'Draft saved successfully.';
    } else {
        $response['message'] = 'Invalid path or directory does not exist.';
    }

} else if ($action === 'delete_draft') {
    $path = $_POST['path'] ?? '';
    if (!$path) {
        $response['message'] = 'Path is required.';
        return;
    }

    $safePath = sanitizePath($path);
    $targetDir = $safePath ? realpath($pagesDir . '/' . $safePath) : $pagesDir;

    if ($targetDir && isPathInDir($targetDir, $pagesDir) && is_dir($targetDir)) {
        $lang = $_POST['lang'] ?? '';
        $draftPath = $targetDir . '/' . getPageFilename($lang, true);
        if (file_exists($draftPath)) {
            if (unlink($draftPath)) {
                $response['success'] = true;
                $response['message'] = 'Draft deleted successfully.';

                // Cleanup empty new page to avoid empty folders
                if (!file_exists($targetDir . '/page.json')) {
                    $hasSubdirs = false;
                    foreach (scandir($targetDir) as $item) {
                        if ($item === '.' || $item === '..') continue;
                        if (is_dir($targetDir . DIRECTORY_SEPARATOR . $item)) {
                            $hasSubdirs = true;
                            break;
                        }
                    }
                    if (!$hasSubdirs) {
                        deleteDirectory($targetDir);
                        invalidateTreeCache();
                        if (function_exists('rebuildNavLinksCache')) rebuildNavLinksCache();
                        if (function_exists('invalidateSearchIndex')) invalidateSearchIndex();
                        clearCache('/' . $safePath);
                    }
                }
            } else {
                $response['message'] = 'Failed to delete draft file.';
            }
        } else {
            $response['message'] = 'Draft does not exist.';
        }
    } else {
        $response['message'] = 'Page does not exist.';
    }
}
