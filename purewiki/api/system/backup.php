<?php
/**
 * PureWiki - Backup API Action
 *
 * Handles API requests for backup management (list, start, delete, download).
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

if ($action === 'list_backups') {
    $backups = getBackups();
    $response['success'] = true;
    $response['data'] = $backups;
}

if ($action === 'get_backup_status') {
    $response['success'] = true;
    $response['running'] = isBackupRunning();
}

if ($action === 'delete_backup') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $response['message'] = 'Invalid request method. POST required.';
    } else {
        $file = $_POST['file'] ?? '';
        if (!$file) {
            $response['message'] = 'No file specified.';
        } else {
            if (deleteBackup($file)) {
                $response['success'] = true;
                $response['message'] = 'Backup deleted.';
            } else {
                $response['message'] = 'Failed to delete backup.';
            }
        }
    }
}

if ($action === 'start_backup') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $response['message'] = 'Invalid request method. POST required.';
    } else {
        if (isBackupRunning()) {
            $response['message'] = 'A backup is already in progress.';
        } else {
            ensureBackupDirectoryExists();

            // createBackup() manages the lock file
            $result = createBackup();

            $response['success'] = true;
            $response['message'] = 'Backup completed.';
        }
    }
}

if ($action === 'download_backup') {
    $file = $_GET['file'] ?? '';
    if (!$file) {
        http_response_code(400);
        echo "No file specified.";
        exit;
    }

    $filepath = getBackupPath($file);

    if ($filepath) {
        $filename = basename($filepath);
        header_remove('Content-Type');
        header('Content-Description: File Transfer');
        header('Content-Type: application/x-tar');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filepath));

        while (ob_get_level()) {
            ob_end_clean();
        }

        readfile($filepath);
        exit;
    } else {
        http_response_code(404);
        echo "File not found.";
        exit;
    }
}
