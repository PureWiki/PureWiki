<?php
/**
 * PureWiki - Media Management
 *
 * Provides utilities for media file listing, image processing, and WebP conversion.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

require_once __DIR__ . '/misc.php';
require_once __DIR__ . '/fs.php';

/**
 * Scans a directory for media files, excluding system JSON files.
 * @param string $dir Absolute path to the directory.
 * @return array List of media files with metadata.
 */
function getMediaFiles($dir) {
    if (!is_dir($dir)) return [];

    $files = [];
    $rawFiles = array_filter(scandir($dir), fn($i) => !in_array($i, ['.', '..', 'page.json', 'page.draft.json', 'page.lock.json']) && is_file($dir . DIRECTORY_SEPARATOR . $i));
    $lookup = array_flip($rawFiles);

    foreach ($rawFiles as $name) {
        if (str_starts_with($name, '.original_')) continue;

        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $baseName = pathinfo($name, PATHINFO_FILENAME);

        if ($ext === 'webp' && (isset($lookup["$baseName.jpg"]) || isset($lookup["$baseName.jpeg"]) || isset($lookup["$baseName.png"]))) {
            continue; // Skip WebP if original exists
        }

        $path = $dir . DIRECTORY_SEPARATOR . $name;
        $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']);

        $icon = match(true) {
            $isImage => 'mdi:file-image-outline',
            $ext === 'pdf' => 'mdi:file-pdf-box',
            in_array($ext, ['zip', 'rar', '7z', 'tar', 'gz']) => 'mdi:zip-box',
            in_array($ext, ['mp3', 'wav', 'ogg']) => 'mdi:file-music-outline',
            in_array($ext, ['mp4', 'webm', 'mov']) => 'mdi:file-video-outline',
            in_array($ext, ['doc', 'docx']) => 'mdi:file-word-outline',
            in_array($ext, ['xls', 'xlsx']) => 'mdi:file-excel-outline',
            default => 'mdi:file-document-outline'
        };

        $files[] = [
            'name' => $name,
            'size' => formatBytes(filesize($path)),
            'type' => $isImage ? 'image' : 'other',
            'icon' => $icon,
            'mtime' => filemtime($path),
            'has_webp' => isset($lookup["$baseName.webp"]),
            'has_backup' => isset($lookup[".original_$name"])
        ];
    }

    usort($files, fn($a, $b) => $b['mtime'] <=> $a['mtime']);
    return $files;
}

/**
 * Converts an image to WebP format if supported.
 * Supports JPEG and PNG sources.
 *
 * @param string $sourcePath Absolute path to the source image.
 * @param int $quality Compression quality (0-100).
 * @return string|false Path to the created WebP file, or false on failure.
 */
function convertToWebP($sourcePath, $quality = 80) {
    if (!file_exists($sourcePath)) return false;

    $info = getimagesize($sourcePath);
    if (!$info) return false;

    $extension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
    $webpPath = pathinfo($sourcePath, PATHINFO_DIRNAME) . '/' . pathinfo($sourcePath, PATHINFO_FILENAME) . '.webp';

    // Don't convert if source is already WebP
    if ($extension === 'webp') return $sourcePath;

    // Check for GD support
    if (function_exists('imagewebp')) {
        $img = null;
        if ($info[2] === IMAGETYPE_JPEG) {
            $img = imagecreatefromjpeg($sourcePath);
        } elseif ($info[2] === IMAGETYPE_PNG) {
            $img = imagecreatefrompng($sourcePath);
            // Preserve transparency
            imagepalettetotruecolor($img);
            imagealphablending($img, true);
            imagesavealpha($img, true);
        }

        if ($img) {
            if (imagewebp($img, $webpPath, $quality)) {
                return $webpPath;
            }
        }
    }

    // Check for Imagick support as fallback
    if (class_exists('Imagick')) {
        try {
            $imagick = new Imagick($sourcePath);
            $formats = $imagick->queryFormats('WEBP');
            if (in_array('WEBP', $formats)) {
                $imagick->setImageFormat('webp');
                $imagick->setImageCompressionQuality($quality);
                if ($imagick->writeImage($webpPath)) {
                    $imagick->destroy();
                    return $webpPath;
                }
            }
            $imagick->destroy();
        } catch (Exception $e) {
            error_log("Imagick conversion failed: " . $e->getMessage());
        }
    }

    return false;
}

/**
 * Checks if a WebP version of a given URL exists.
 * @param string $url The image URL (relative to root).
 * @return string The WebP URL if it exists, otherwise the original URL.
 */
function getWebpUrl($url) {
    if (!$url) return $url;
    // Only local files
    if (str_starts_with($url, 'http') || str_starts_with($url, '//')) return $url;

    $relPath = ltrim(parse_url($url, PHP_URL_PATH), '/');
    $absPath = __DIR__ . '/../../' . $relPath;

    $webpPath = pathinfo($absPath, PATHINFO_DIRNAME) . '/' . pathinfo($absPath, PATHINFO_FILENAME) . '.webp';

    if (file_exists($webpPath)) {
        return pathinfo($url, PATHINFO_DIRNAME) . '/' . pathinfo($url, PATHINFO_FILENAME) . '.webp';
    }
    return $url;
}

/**
 * Renames a media file and its associated derivatives (.webp, .original_).
 *
 * @param string $oldFilePath The absolute path to the current file.
 * @param string $newFilePath The absolute path to the new file.
 * @return bool True if the main file was successfully renamed.
 */
function renameMedia($oldFilePath, $newFilePath) {
    if (!file_exists($oldFilePath) || !is_file($oldFilePath)) return false;

    if (rename($oldFilePath, $newFilePath)) {
        $oldExt = strtolower(pathinfo($oldFilePath, PATHINFO_EXTENSION));
        if (in_array($oldExt, ['jpg', 'jpeg', 'png'])) {
            $oldWebpPath = pathinfo($oldFilePath, PATHINFO_DIRNAME) . '/' . pathinfo($oldFilePath, PATHINFO_FILENAME) . '.webp';
            if (file_exists($oldWebpPath)) {
                $newWebpPath = pathinfo($newFilePath, PATHINFO_DIRNAME) . '/' . pathinfo($newFilePath, PATHINFO_FILENAME) . '.webp';
                rename($oldWebpPath, $newWebpPath);
            }
        }

        $targetDir = pathinfo($oldFilePath, PATHINFO_DIRNAME);
        $oldOriginalPath = $targetDir . DIRECTORY_SEPARATOR . '.original_' . basename($oldFilePath);
        if (file_exists($oldOriginalPath)) {
            $newOriginalPath = pathinfo($newFilePath, PATHINFO_DIRNAME) . DIRECTORY_SEPARATOR . '.original_' . basename($newFilePath);
            rename($oldOriginalPath, $newOriginalPath);
        }
        return true;
    }
    return false;
}

/**
 * Deletes a file and its WebP counterpart if it exists.
 */
function deleteMediaWithDerivatives($absPath) {
    if (!file_exists($absPath) || !is_file($absPath)) return false;

    $extension = strtolower(pathinfo($absPath, PATHINFO_EXTENSION));
    if (unlink($absPath)) {
        if (in_array($extension, ['jpg', 'jpeg', 'png'])) {
            $webpPath = pathinfo($absPath, PATHINFO_DIRNAME) . '/' . pathinfo($absPath, PATHINFO_FILENAME) . '.webp';
            if (file_exists($webpPath)) {
                unlink($webpPath);
            }
        }
        return true;
    }
    return false;
}

/**
 * Scans all pages for JPG/PNG images and converts them to WebP.
 */
function bulkConvertWebP() {
    $lockFile = __DIR__ . '/../webp_conversion.lock';

    if (isLockActive($lockFile)) {
        return;
    }

    file_put_contents($lockFile, time());

    try {
        $pagesDir = getPageDir();
        if (!$pagesDir) {
            return;
        }

        // CATCH_GET_CHILD skips unreadable subdirectories instead of throwing
        $dirIterator = new RecursiveDirectoryIterator(
            $pagesDir,
            RecursiveDirectoryIterator::SKIP_DOTS
        );
        $iterator = new RecursiveIteratorIterator(
            $dirIterator,
            RecursiveIteratorIterator::LEAVES_ONLY,
            RecursiveIteratorIterator::CATCH_GET_CHILD
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) continue;
            if (str_starts_with($file->getFilename(), '.original_')) continue;

            $ext = strtolower($file->getExtension());
            if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
                convertToWebP($file->getPathname());
            }
        }
    } finally {
        // Always remove the lock, even if an exception is thrown
        if (file_exists($lockFile)) {
            unlink($lockFile);
        }
    }
}

/**
 * Checks if a file extension is allowed based on global configuration and a hardcoded blacklist.
 *
 * @param string $extension The file extension to check.
 * @return bool True if allowed, false otherwise.
 */
function isMediaExtensionAllowed($extension) {
    $extension = strtolower(trim($extension));
    if ($extension === '') return false;

    // Hardcoded blacklist
    $blacklist = ['php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'exe', 'sh', 'bat', 'cgi', 'pl', 'jsp', 'asp', 'aspx', 'py', 'rb'];
    if (in_array($extension, $blacklist)) {
        return false;
    }

    // Configurable whitelist
    $configObj = getGlobalConfig();
    $allowedExtensionsRaw = $configObj['allowed_file_extensions'] ?? 'jpg, jpeg, png, gif, svg, webp, pdf, mp4, webm, zip, csv, txt';
    $allowedExtensions = array_filter(array_map('trim', explode(',', strtolower($allowedExtensionsRaw))));

    if (!empty($allowedExtensions) && !in_array($extension, $allowedExtensions)) {
        return false;
    }

    return true;
}