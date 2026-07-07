<?php
/**
 * PureWiki - Comment Management Core
 *
 * Core functions for comment storage, moderation, and CRUD tasks
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

require_once __DIR__ . '/json.php';
require_once __DIR__ . '/fs.php';
require_once __DIR__ . '/config.php';

/**
 * Returns the absolute path to the comments.json of a given page path
 *
 * @param string $pagePath Relative page path (e.g. '/' or 'about-us')
 * @return string Absolute file path
 */
function getCommentsFilePath(string $pagePath): string {
    $pagesDir = getPageDir();
    $cleanPath = sanitizePath($pagePath);
    if ($cleanPath === '' || $cleanPath === '/') {
        return $pagesDir . '/comments.json';
    }
    return $pagesDir . '/' . ltrim($cleanPath, '/') . '/comments.json';
}

/**
 * Reads all comments of a page
 *
 * @param string $pagePath Relative page path
 * @return array Array of comments
 */
function getComments(string $pagePath): array {
    $file = getCommentsFilePath($pagePath);
    if (!file_exists($file)) {
        return [];
    }
    try {
        $data = readJsonFile($file);
        return (is_array($data) && isset($data['comments']) && is_array($data['comments'])) ? $data['comments'] : [];
    } catch (PureWikiException $exception) {
        return [];
    }
}

/**
 * Saves comments array to comments.json file of a page
 *
 * @param string $pagePath Relative page path
 * @param array $comments Comments array to save
 * @return bool True on success
 */
function saveComments(string $pagePath, array $comments): bool {
    $file = getCommentsFilePath($pagePath);
    $dir = dirname($file);
    if (!is_dir($dir)) {
        createDirectory($dir);
    }
    return writeJsonFile($file, ['comments' => array_values($comments)]);
}

/**
 * Adds a new comment to a page
 *
 * @param string $pagePath Relative page path
 * @param string $name User name
 * @param string $email User email
 * @param string $text Comment text
 * @return array The created comment details
 */
function addComment(string $pagePath, string $name, string $email, string $text): array {
    $comments = getComments($pagePath);
    $config = getGlobalConfig();
    
    // Sanitization
    $cleanName = trim($name);
    $cleanEmail = trim($email);
    $cleanText = trim($text);
    
    // Check spam regex
    $isSpam = false;
    $spamRegexList = $config['comments_spam_regex'] ?? [];
    if (is_array($spamRegexList) && !empty($spamRegexList)) {
        foreach ($spamRegexList as $pattern) {
            if (empty($pattern)) continue;
            
            $delimitedPattern = $pattern;
            $firstChar = substr($pattern, 0, 1);
            $delimiters = ['/', '#', '~', '@'];
            if (in_array($firstChar, $delimiters)) {
                $delimitedPattern = $pattern;
            } else {
                $delimitedPattern = '/' . str_replace('/', '\/', $pattern) . '/i';
            }
            
            try {
                if (@preg_match($delimitedPattern, $cleanEmail)) {
                    $isSpam = true;
                    break;
                }
            } catch (\Throwable $e) {
                // Ignore invalid regex
            }
        }
    }
    
    // Approve if approval settings are disabled and no spam
    $status = !empty($config['comments_require_approval']) ? 'pending' : 'approved';
    if ($isSpam) {
        $status = 'pending';
    }
    
    $newComment = [
        'id' => 'c_' . time() . '_' . bin2hex(random_bytes(3)),
        'name' => $cleanName,
        'email' => $cleanEmail,
        'text' => $cleanText,
        'date' => date(DATE_ISO8601),
        'status' => $status,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? ''
    ];
    
    if ($isSpam) {
        $newComment['spam'] = true;
    }
    
    $comments[] = $newComment;
    saveComments($pagePath, $comments);
    
    return $newComment;
}

/**
 * Finds index of a comment by ID in comments array
 *
 * @param array $comments The comments list to search
 * @param string $id Comment ID to find
 * @return int|null Index of comment or null if not found
 */
function findCommentById(array $comments, string $id): ?int {
    foreach ($comments as $index => $comment) {
        if (($comment['id'] ?? '') === $id) {
            return $index;
        }
    }
    return null;
}

/**
 * Updates status of a comment
 *
 * @param string $pagePath Relative page path
 * @param string $commentId Comment ID
 * @param string $newStatus New status ('approved', 'hidden', 'pending')
 * @return bool True on success
 */
function updateCommentStatus(string $pagePath, string $commentId, string $newStatus): bool {
    $comments = getComments($pagePath);
    $index = findCommentById($comments, $commentId);
    if ($index === null) {
        return false;
    }
    $comments[$index]['status'] = $newStatus;
    return saveComments($pagePath, $comments);
}

/**
 * Deletes a comment
 *
 * @param string $pagePath Relative page path
 * @param string $commentId Comment ID
 * @return bool True on success
 */
function deleteComment(string $pagePath, string $commentId): bool {
    $comments = getComments($pagePath);
    $index = findCommentById($comments, $commentId);
    if ($index === null) {
        return false;
    }
    array_splice($comments, $index, 1);
    return saveComments($pagePath, $comments);
}

/**
 * Returns number of pending comments for a page
 *
 * @param string $pagePath Relative page path
 * @return int Number of pending comments
 */
function countPendingComments(string $pagePath): int {
    $comments = getComments($pagePath);
    $count = 0;
    foreach ($comments as $comment) {
        if (($comment['status'] ?? '') === 'pending') {
            $count++;
        }
    }
    return $count;
}

/**
 * Scans all pages recursively to collect all comments
 *
 * @return array List of all comments sorted by date desc
 */
function getAllComments(): array {
    $pagesDir = getPageDir();
    $allComments = [];

    // Root comments
    $rootCommentsFile = $pagesDir . '/comments.json';
    if (file_exists($rootCommentsFile)) {
        try {
            $data = readJsonFile($rootCommentsFile);
            if (isset($data['comments']) && is_array($data['comments'])) {
                foreach ($data['comments'] as $comment) {
                    $comment['page_path'] = '/';
                    $allComments[] = $comment;
                }
            }
        } catch (PureWikiException $exception) {
            // ignore corrupted files
        }
    }

    // Scan directories recursively
    $scanDir = function(string $dir, string $basePath) use (&$scanDir, &$allComments) {
        if (!is_dir($dir)) return;
        $items = scandir($dir);
        if ($items === false) return;

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            if (str_starts_with($item, '_')) continue;

            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $relativePath = ltrim($basePath . '/' . $item, '/');
                $commentsFile = $path . DIRECTORY_SEPARATOR . 'comments.json';
                if (file_exists($commentsFile)) {
                    try {
                        $data = readJsonFile($commentsFile);
                        if (isset($data['comments']) && is_array($data['comments'])) {
                            foreach ($data['comments'] as $comment) {
                                $comment['page_path'] = '/' . $relativePath;
                                $allComments[] = $comment;
                            }
                        }
                    } catch (PureWikiException $exception) {
                        // ignore corrupted files
                    }
                }
                $scanDir($path, $relativePath);
            }
        }
    };

    $scanDir($pagesDir, '');

    usort($allComments, function($a, $b) {
        return strcmp($b['date'] ?? '', $a['date'] ?? '');
    });

    return $allComments;
}
