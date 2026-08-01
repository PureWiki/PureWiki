<?php
/**
 * PureWiki - System Status API
 *
 * Returns information about the current Wiki version and server environment.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

require_once __DIR__ . '/../../core/fs.php';

if ($action === 'get_system_status') {
    // get basic system info
    $version = PUREWIKI_VERSION;
    $phpVersion = PHP_VERSION;
    $os = PHP_OS_FAMILY;

    // get WebP support status
    $webpSupported = false;
    $webpEngine = 'None';

    if (function_exists('imagewebp')) {
        $webpSupported = true;
        $webpEngine = 'GD';
    } elseif (class_exists('Imagick')) {
        $imagick = new Imagick();
        $formats = $imagick->queryFormats('WEBP');
        if (in_array('WEBP', $formats)) {
            $webpSupported = true;
            $webpEngine = 'Imagick';
        }
    }

    // Get image stats
    $totalImages = 0;
    $webpOptimized = 0;
    $pagesDir = getPageDir();

    if ($pagesDir && is_dir($pagesDir)) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($pagesDir));
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                if (str_starts_with($file->getFilename(), '.original_')) {
                    continue;
                }
                $ext = strtolower($file->getExtension());
                if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
                    $totalImages++;
                    $webpFile = $file->getPath() . DIRECTORY_SEPARATOR . $file->getBasename('.' . $file->getExtension()) . '.webp';
                    if (file_exists($webpFile)) {
                        $webpOptimized++;
                    }
                }
            }
        }
    }

    $response['success'] = true;
    $response['data'] = [
        'version' => $version,
        'php_version' => $phpVersion,
        'os' => $os,
        'webp_enabled' => $webpSupported,
        'webp_engine' => $webpEngine,
        'stats' => [
            'total_images' => $totalImages,
            'webp_optimized' => $webpOptimized,
            'optimization_ratio' => $totalImages > 0 ? round(($webpOptimized / $totalImages) * 100) : 100
        ],
        'storage' => getDiskStorageInfo()
    ];
}

