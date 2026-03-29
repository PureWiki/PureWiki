<?php
/**
 * PureWiki - JSON Read/Write
 *
 * Functions for reading and writing JSON files
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

require_once __DIR__ . '/exception.php';

/**
 * Reads a JSON file and parses it into an associative array.
 * @param string $filePath Path to the JSON file.
 * @return array|null The parsed array or null on failure.
 */
function readJsonFile($filePath) {
    if (!file_exists($filePath)) {
        throw new PureWikiException("File not found: " . basename($filePath));
    }
    $content = file_get_contents($filePath);
    if ($content === false) {
        throw new PureWikiException("Failed to read file: " . basename($filePath));
    }
    $decoded = json_decode($content, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new PureWikiException("Invalid JSON in file: " . basename($filePath));
    }
    return $decoded;
}

/**
 * Reads a JSON file and return default value if file does not exist or parsing fails.
 * @param string $filePath
 * @param mixed $default
 * @return mixed Parsed array or the default value
 */
function readJson($filePath, $default = []) {
    try {
        return readJsonFile($filePath);
    } catch (PureWikiException $e) {
        return $default;
    }
}

/**
 * Writes an associative array to a JSON file.
 * @param string $filePath Path to the JSON file.
 * @param array $data The data to write.
 * @return bool True on success, false on failure.
 */
function writeJsonFile($filePath, $data) {
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if (file_put_contents($filePath, $json) === false) {
        throw new PureWikiException("Failed to write to file: " . basename($filePath));
    }
    @chmod($filePath, 0644);
    return true;
}
