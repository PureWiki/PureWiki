<?php
/**
 * PureWiki - Miscellaneous Utilities
 *
 * General-purpose utility functions
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

/**
 * Formats bytes into a human-readable string.
 */
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

/** Returns a php.ini size value like 8M in bytes, 0 means no limit. */
function iniSizeToBytes(string $value): int {
    $value = trim($value);
    $number = (int)$value;
    return match (strtolower(substr($value, -1))) {
        'g' => $number * 1024 ** 3,
        'm' => $number * 1024 ** 2,
        'k' => $number * 1024,
        default => $number,
    };
}

/**
 * Converts a slug or folder name into a human-readable title.
 */
function prepareTitle(string $slug): string {
    return ucwords(str_replace(['-', '_'], ' ', $slug));
}

/** Replaces special characters with ASCII */
function replaceSpecialChars(string $text): string {
    return str_replace(
        ['Ä', 'Ö', 'Ü', 'ä', 'ö', 'ü', 'ß'],
        ['Ae', 'Oe', 'Ue', 'ae', 'oe', 'ue', 'ss'],
        $text
    );
}

/**
 * Generates a safe folder name (slug) from a title.
 * Handles spaces, umlauts, and special characters.
 * @param string $title The raw title to sanitize.
 * @return string The sanitized folder name.
 */
function generateSlug(string $title): string {
    $slug = replaceSpecialChars($title);
    $slug = preg_replace('/[^a-zA-Z0-9_-]/', '_', str_replace(' ', '_', $slug));
    $slug = preg_replace('/_+/', '_', $slug);
    return strtolower(trim($slug, '_'));
}
