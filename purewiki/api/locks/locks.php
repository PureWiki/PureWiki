<?php
/**
 * PureWiki - Page Locking Action
 *
 * Manages concurrent editing prevention. Handles acquiring,
 * refreshing, and releasing locks for specific pages.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

if ($action === 'acquire_lock') {
    $lockPath = $_POST['path'] ?? '';
    $safeLockPath = sanitizePath($lockPath);
    $isVirtual = str_starts_with($safeLockPath, '_');

    // create virtual page directories to allow lock file creation
    // Necessary because virtual pages are not created until save but used from the data directory
    if ($isVirtual) {
        $vDir = $pagesDir . '/' . $safeLockPath;
        if (!file_exists($vDir)) {
            @createDirectory($vDir);
        }
    }

    $lockDir = $safeLockPath ? realpath($pagesDir . '/' . $safeLockPath) : $pagesDir;

    if ($lockDir && isPathInDir($lockDir, $pagesDir) && is_dir($lockDir)) {
        $lockFile = $lockDir . '/page.lock.json';
        $currentUser = $_SESSION['pw_user'] ?? 'unknown';
        $now = time();
        $lockTtl = 20 * 60; // Locks expire after 20 minutes of inactivity

        if (file_exists($lockFile)) {
            $lock = readJson($lockFile);
            if (is_array($lock) && ($lock['user'] ?? '') !== $currentUser && $now < ($lock['expires_at'] ?? 0)) {
                $response['message'] = 'Page is locked by ' . htmlspecialchars($lock['user'] ?? 'another user');
                $response['locked_by'] = $lock['user'] ?? '';
                $response['locked_until'] = $lock['expires_at'] ?? 0;
                return;
            }
        }

        $lock = [
            'user'        => $currentUser,
            'acquired_at' => $now,
            'expires_at'  => $now + $lockTtl
        ];
        writeJsonFile($lockFile, $lock);
        $response['success'] = true;
        $response['message'] = 'Lock acquired.';
    } else {
        $response['message'] = 'Invalid path.';
    }

} else if ($action === 'release_lock') {
    $lockPath = $_POST['path'] ?? '';
    $safeLockPath = sanitizePath($lockPath);

    $lockDir = $safeLockPath ? realpath($pagesDir . '/' . $safeLockPath) : $pagesDir;

    if ($lockDir && isPathInDir($lockDir, $pagesDir)) {
        $lockFile = $lockDir . '/page.lock.json';
        $currentUser = $_SESSION['pw_user'] ?? 'unknown';

        if (file_exists($lockFile)) {
            $lock = readJson($lockFile);
            if (($lock['user'] ?? '') === $currentUser) {
                unlink($lockFile);
                $response['success'] = true;
                $response['message'] = 'Lock released.';
            } else {
                $response['message'] = 'You do not own this lock.';
            }
        } else {
            $response['success'] = true;
            $response['message'] = 'No lock to release.';
        }
    } else {
        $response['message'] = 'Invalid path.';
    }

} else if ($action === 'refresh_lock') {
    $lockPath = $_POST['path'] ?? '';
    $safeLockPath = sanitizePath($lockPath);

    $lockDir = $safeLockPath ? realpath($pagesDir . '/' . $safeLockPath) : $pagesDir;

    if ($lockDir && isPathInDir($lockDir, $pagesDir)) {
        $lockFile = $lockDir . '/page.lock.json';
        $currentUser = $_SESSION['pw_user'] ?? 'unknown';

        if (file_exists($lockFile)) {
            $lock = readJson($lockFile);
            if (($lock['user'] ?? '') === $currentUser) {
                $lock['expires_at'] = time() + 20 * 60;
                writeJsonFile($lockFile, $lock);
                $response['success'] = true;
                $response['message'] = 'Lock refreshed.';
            } else {
                $response['message'] = 'You do not own this lock.';
            }
        } else {
            $response['message'] = 'Lock not found.';
        }
    } else {
        $response['message'] = 'Invalid path.';
    }
}
