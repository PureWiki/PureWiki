<?php
/**
 * PureWiki - Content Publishing
 *
 * Handles making drafts live. Moves the current draft to the production
 * file while archiving the previous live version in the history
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

require_once __DIR__ . '/../../core/i18n_pages.php';

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
    $publishPath = $targetDir . '/' . getPageFilename($lang, false);

    if (file_exists($draftPath)) {
        if (file_exists($publishPath)) {
            $config = getGlobalConfig();
            $enableHistory = $config['enable_history'] ?? true;
            $maxVersions = (int)($config['history_max_versions'] ?? 20);

            if ($enableHistory) {
                $historyDir = $targetDir . '/_history';
                if (!is_dir($historyDir)) createDirectory($historyDir);
                $oldData = readJsonFile($publishPath);
                $dateCode = date('YmdHis');
                if ($oldData && !empty($oldData['DateModified'])) {
                    $dateCode = date('YmdHis', strtotime($oldData['DateModified']));
                }
                $historyFilename = 'page.' . ($lang ? $lang . '.' : '') . $dateCode . '.json';
                copy($publishPath, $historyDir . '/' . $historyFilename);

                // Prune history: keep max configured entries, delete oldest
                $histFiles = glob($historyDir . '/page.' . ($lang ? $lang . '.' : '') . '*.json');
                if (!$lang) {
                    $histFiles = array_filter($histFiles, function($f) {
                        return preg_match('/page\.\d+\.json$/', basename($f));
                    });
                }
                if (count($histFiles) > $maxVersions) {
                    sort($histFiles); // oldest first (filenames contain timestamps)
                    $toDelete = array_slice($histFiles, 0, count($histFiles) - $maxVersions);
                    foreach ($toDelete as $old) {
                        unlink($old);
                    }
                }
            }
        }

        if (copy($draftPath, $publishPath)) {
            unlink($draftPath);

            // Invalidate tree before rebuilding nav links to ensure the new
            // page tree (without draft flags) is used for the navbar.
            invalidateTreeCache();
            invalidateSearchIndex();

            // Rebuild nav links for all configured languages
            rebuildNavLinksCache(''); // default language
            $config = getGlobalConfig();
            // rebuild nav links for all configured languages
            foreach ($config['i18n_supported_langs'] ?? [] as $supportedLang) {
                rebuildNavLinksCache($supportedLang);
            }

            $response['success'] = true;
            $response['message'] = 'Page published successfully.';
            logActivity('page_publish', 'page', $safePath);

            // Clear only the specific page's HTML cache to avoid full cache rebuilds.
            clearCache('/' . $safePath);
        } else {
            $response['message'] = 'Failed to copy draft to published file.';
        }
    } else {
        $response['success'] = true; // No draft means already published
        $response['message'] = 'No draft found to publish.';
    }
} else {
    $response['message'] = 'Page does not exist.';
}
