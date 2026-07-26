<?php
/**
 * PureWiki - Media API
 *
 * API actions for listing, uploading, renaming, and deleting media files in pages.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

if ($action === 'list_media') {
    $path = $_POST['path'] ?? '__global__';

    if ($path === '__global__') {
        $targetDir = $pagesDir;
    } else {
        $safePath = sanitizePath($path);
        $targetDir = realpath($pagesDir . '/' . $safePath);
    }

    if ($targetDir && isPathInDir($targetDir, $pagesDir) && is_dir($targetDir)) {
        $response['success'] = true;
        $response['data'] = getMediaFiles($targetDir);
    } else {
        $response['message'] = 'Invalid path or directory not found.';
    }

} else if ($action === 'upload_media') {
    $path = $_POST['path'] ?? '__global__';

    if ($path === '__global__') {
        $targetDir = $pagesDir;
    } else {
        $safePath = sanitizePath($path);
        $targetDir = realpath($pagesDir . '/' . $safePath);
    }

    if ($targetDir && isPathInDir($targetDir, $pagesDir) && is_dir($targetDir)) {
        if (empty($_FILES['files'])) {
            $response['message'] = 'No files uploaded.';
            return;
        }

        $files = $_FILES['files'];
        $overwrite = isset($_POST['overwrite']) && $_POST['overwrite'] === 'true';
        $uploadedCount = 0;
        $errors = [];
        $existingFiles = [];

        $count = is_array($files['name']) ? count($files['name']) : 1;

        if (!$overwrite) {
            for ($i = 0; $i < $count; $i++) {
                $name = is_array($files['name']) ? $files['name'][$i] : $files['name'];
                $destPath = $targetDir . DIRECTORY_SEPARATOR . basename($name);
                if (file_exists($destPath)) {
                    $existingFiles[] = $name;
                }
            }

            if (!empty($existingFiles)) {
                $response['success'] = false;
                $response['require_confirmation'] = true;
                $response['existing_files'] = $existingFiles;
                return;
            }
        }

        for ($i = 0; $i < $count; $i++) {
            $name = is_array($files['name']) ? $files['name'][$i] : $files['name'];
            $tmpName = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
            $error = is_array($files['error']) ? $files['error'][$i] : $files['error'];

            if ($error === UPLOAD_ERR_OK) {
                $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

                require_once __DIR__ . '/../../core/media.php';
                if (!isMediaExtensionAllowed($extension)) {
                    $errors[] = "$name: File type '$extension' is not allowed or prohibited for security reasons.";
                    continue;
                }

                $destPath = $targetDir . DIRECTORY_SEPARATOR . basename($name);

                $systemFiles = ['page.json', 'page.draft.json', 'page.lock.json'];
                if (in_array($name, $systemFiles)) {
                    $errors[] = "$name: Cannot overwrite system files.";
                    continue;
                }

                if ($overwrite && file_exists($destPath)) {
                    require_once __DIR__ . '/../../core/media.php';
                    deleteMediaWithDerivatives($destPath);
                }

                if (move_uploaded_file($tmpName, $destPath)) {
                    $uploadedCount++;

                    // Automatic WebP Conversion for images
                    $isImage = in_array($extension, ['jpg', 'jpeg', 'png']);
                    if ($isImage) {
                        convertToWebP($destPath);
                    }
                } else {
                    $errors[] = "$name: Failed to move to destination.";
                }
            } else {
                $errors[] = "$name: Upload error code $error.";
            }
        }

        $response['success'] = $uploadedCount > 0;
        
        if ($uploadedCount > 0) {
            $response['message'] = "$uploadedCount file(s) uploaded successfully.";
            logActivity('media_upload', 'media', $path === '__global__' ? '/' : '/' . ($safePath ?? ''), ['count' => $uploadedCount]);
            if (!empty($errors)) {
                $response['errors'] = $errors;
                $response['message'] .= " (" . count($errors) . " failed)";
            }
        } else {
            $response['message'] = implode("\n", $errors);
            $response['errors'] = $errors;
        }
    } else {
        $response['message'] = 'Invalid upload destination.';
    }

} else if ($action === 'delete_media') {
    $path = $_POST['path'] ?? '__global__';
    $filename = $_POST['filename'] ?? '';

    if (!$filename) {
        $response['message'] = 'Filename is required.';
        return;
    }

    if ($path === '__global__') {
        $targetDir = $pagesDir;
    } else {
        $safePath = sanitizePath($path);
        $targetDir = realpath($pagesDir . '/' . $safePath);
    }

    if ($targetDir && isPathInDir($targetDir, $pagesDir) && is_dir($targetDir)) {
        $fileToDelete = $targetDir . DIRECTORY_SEPARATOR . basename($filename);

        $systemFiles = ['page.json', 'page.draft.json', 'page.lock.json'];
        if (in_array(basename($filename), $systemFiles)) {
            $response['message'] = 'Cannot delete system files.';
            return;
        }

        if (file_exists($fileToDelete) && is_file($fileToDelete)) {
            if (deleteMediaWithDerivatives($fileToDelete)) {
                $response['success'] = true;
                $response['message'] = 'File deleted successfully.';
                logActivity('media_delete', 'media', basename($filename));
            } else {
                $response['message'] = 'Failed to delete file.';
            }
        } else {
            $response['message'] = 'File not found.';
        }
    } else {
        $response['message'] = 'Invalid path.';
    }

} else if ($action === 'rename_media') {
    $path = $_POST['path'] ?? '__global__';
    $oldName = $_POST['old_name'] ?? '';
    $newName = $_POST['new_name'] ?? '';

    if (!$oldName || !$newName) {
        $response['message'] = 'Old and new filenames are required.';
        return;
    }

    // Basic sanitization
    $newName = basename($newName);
    if ($newName === '' || $newName === '.' || $newName === '..') {
        $response['message'] = 'Invalid new filename.';
        return;
    }

    // Automatically append original extension if missing
    $newExtension = strtolower(pathinfo($newName, PATHINFO_EXTENSION));
    $oldExtension = strtolower(pathinfo($oldName, PATHINFO_EXTENSION));
    if ($newExtension === '' && $oldExtension !== '') {
        $newName .= '.' . $oldExtension;
        $newExtension = $oldExtension;
    }

    $systemFiles = ['page.json', 'page.draft.json', 'page.lock.json'];
    if (in_array(basename($oldName), $systemFiles) || in_array($newName, $systemFiles)) {
        $response['message'] = 'Cannot rename system files.';
        return;
    }

    if ($path === '__global__') {
        $targetDir = $pagesDir;
    } else {
        $safePath = sanitizePath($path);
        $targetDir = realpath($pagesDir . '/' . $safePath);
    }

    if ($targetDir && isPathInDir($targetDir, $pagesDir) && is_dir($targetDir)) {
        $oldFilePath = $targetDir . DIRECTORY_SEPARATOR . basename($oldName);
        $newFilePath = $targetDir . DIRECTORY_SEPARATOR . $newName;

        if (!file_exists($oldFilePath) || !is_file($oldFilePath)) {
            $response['message'] = 'File not found.';
            return;
        }

        if (file_exists($newFilePath)) {
            $response['message'] = 'A file with the new name already exists.';
            return;
        }

        $extension = $newExtension;

        // Ensure files have an extension (prevent renaming to extensionless files)
        if ($extension === '') {
             $response['message'] = "File must have an extension.";
             return;
        }

        if (!isMediaExtensionAllowed($extension)) {
            $response['message'] = "File type '$extension' is not allowed for security reasons.";
            return;
        }

        if (renameMedia($oldFilePath, $newFilePath)) {
            $response['success'] = true;
            $response['message'] = 'File renamed successfully.';
        } else {
            $response['message'] = 'Failed to rename file.';
        }
    } else {
        $response['message'] = 'Invalid path.';
    }
}
