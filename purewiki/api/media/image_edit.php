<?php
/**
 * PureWiki - Image Editing API
 *
 * Handles backend operations for the frontend image cropper.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

$action = $_POST['action'] ?? '';
$path = $_POST['path'] ?? '';
$filename = $_POST['filename'] ?? '';

if (!$path || !$filename) {
    $response['message'] = 'Path and filename are required.';
    return;
}

$safePath = sanitizePath($path);
// Allow operations on the global media folder or specific page folders
$targetDir = ($path === '__global__') ? $pagesDir : realpath($pagesDir . '/' . $safePath);

if (!$targetDir || !is_dir($targetDir)) {
    $response['message'] = 'Target directory not found.';
    return;
}

$imagePath = $targetDir . '/' . basename($filename);
$backupPath = $targetDir . '/.original_' . basename($filename);

if (!file_exists($imagePath) || !is_file($imagePath)) {
    $response['message'] = 'Image not found.';
    return;
}

$ext = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));
if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
    $response['message'] = 'Only JPG, PNG, and WebP images can be edited.';
    return;
}

if ($action === 'backup_image') {
    if (!file_exists($backupPath)) {
        if (copy($imagePath, $backupPath)) {
            $response['success'] = true;
            $response['message'] = 'Backup created.';
        } else {
            $response['message'] = 'Failed to create backup.';
        }
    } else {
        $response['success'] = true;
        $response['message'] = 'Backup already exists.';
    }
} else if ($action === 'restore_image') {
    if (file_exists($backupPath)) {
        if (copy($backupPath, $imagePath)) {
            // Also need to regenerate the WebP if there is one
            convertToWebP($imagePath);
            // Remove the backup file (original image)
            unlink($backupPath);
            $response['success'] = true;
            $response['message'] = 'Image restored from backup.';
        } else {
            $response['message'] = 'Failed to restore image.';
        }
    } else {
        $response['message'] = 'No backup found.';
    }
} else if ($action === 'process_image_edit') {
    $imageData = $_POST['image_data'] ?? '';
    if (!$imageData) {
        $response['message'] = 'No image data provided.';
        return;
    }

    // Ensure backup of image exists before overwriting
    if (!file_exists($backupPath)) {
        copy($imagePath, $backupPath);
    }

    // Extract base64 data (e.g., "data:image/png;base64,iVBORw0KGgo...")
    $parts = explode(',', $imageData);
    if (count($parts) === 2) {
        $decodedData = base64_decode($parts[1]);
        if ($decodedData !== false) {
            if (file_put_contents($imagePath, $decodedData) !== false) {
                // Regenerate the WebP if there is one
                convertToWebP($imagePath);

                $response['success'] = true;
                $response['message'] = 'Image successfully cropped and saved.';
            } else {
                $response['message'] = 'Failed to write image data to file.';
            }
        } else {
            $response['message'] = 'Invalid base64 encoding.';
        }
    } else {
        $response['message'] = 'Invalid image data format.';
    }
}
