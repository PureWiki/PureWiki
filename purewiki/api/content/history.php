<?php
/**
 * PureWiki - Content History API
 *
 * Manages page versioning and restoration. Returns historical versions and handles draft restoration.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

if ($action === 'get_page_history') {
    $path = $_POST['path'] ?? '';
    if (!$path) {
        $response['message'] = 'Path is required.';
        return;
    }

    $safePath = sanitizePath($path);
    $targetDir = $safePath ? realpath($pagesDir . '/' . $safePath) : $pagesDir;

    if ($targetDir && isPathInDir($targetDir, $pagesDir) && is_dir($targetDir)) {
        $historyDir = $targetDir . '/_history';
        $versions = [];

        if (is_dir($historyDir)) {
            $lang = $_POST['lang'] ?? '';
            $globPattern = $historyDir . '/page.' . ($lang ? $lang . '.' : '') . '*.json';
            $files = glob($globPattern);
            
            $regex = '/^page\.' . ($lang ? preg_quote($lang) . '\.' : '') . '(\d{14})\.json$/';
            
            foreach ($files as $file) {
                $basename = basename($file);
                if (preg_match($regex, $basename, $m)) {
                    $dt = DateTime::createFromFormat('YmdHis', $m[1]);
                    if ($dt) {
                        $author = '';
                        if (file_exists($file)) {
                            $historyData = readJson($file, null);
                            if ($historyData) {
                                $author = $historyData['LastEditor'] ?? ($historyData['Author'] ?? '');
                            }
                        }

                        $versions[] = [
                            'file' => $basename,
                            'date' => $dt->format('d.m.Y'),
                            'time' => $dt->format('H:i'),
                            'timestamp' => (int)$dt->format('U'),
                            'author' => $author
                        ];
                    }
                }
            }
            usort($versions, fn($a, $b) => $b['timestamp'] - $a['timestamp']);
        }

        $response['success'] = true;
        $response['data'] = $versions;
    } else {
        $response['message'] = 'Page does not exist.';
    }

} else if ($action === 'restore_page_version') {
    $path = $_POST['path'] ?? '';
    $file = $_POST['file'] ?? '';

    if (!$path || !$file) {
        $response['message'] = 'Path and file are required.';
        return;
    }

    $safePath = sanitizePath($path);
    $targetDir = $safePath ? realpath($pagesDir . '/' . $safePath) : $pagesDir;
    $safeFile = basename($file);

    if ($targetDir && isPathInDir($targetDir, $pagesDir) && is_dir($targetDir)) {
        $historyFile = $targetDir . '/_history/' . $safeFile;
        $lang = $_POST['lang'] ?? '';
        
        $regex = '/^page\.' . ($lang ? preg_quote($lang) . '\.' : '') . '\d{14}\.json$/';

        if (file_exists($historyFile) && preg_match($regex, $safeFile)) {
            if (!function_exists('getPageFilename')) {
                require_once __DIR__ . '/../../core/i18n_pages.php';
            }
            $draftPath = $targetDir . '/' . getPageFilename($lang, true);
            if (copy($historyFile, $draftPath)) {
                $response['success'] = true;
                $response['message'] = 'Version restored as draft.';
            } else {
                $response['message'] = 'Failed to restore version.';
            }
        } else {
            $response['message'] = 'History file not found or invalid.';
        }
    } else {
        $response['message'] = 'Page does not exist.';
    }
}
