<?php
/**
 * PureWiki - API: list_extensions
 *
 * Returns all discovered extensions with their meta.json data
 * and the current enabled state from config.json.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

$extensionsDir = getExtensionsDir();
$config        = getGlobalConfig();
$states        = $config['extensions'] ?? [];

$result = [];

if (is_dir($extensionsDir)) {
    $entries = scandir($extensionsDir);
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        $extDir = $extensionsDir . DIRECTORY_SEPARATOR . $entry;
        if (!is_dir($extDir)) continue;

        $metaFile = $extDir . DIRECTORY_SEPARATOR . 'meta.json';
        if (!file_exists($metaFile)) continue;

        $raw  = file_get_contents($metaFile);
        $meta = $raw !== false ? json_decode($raw, true) : null;
        if (!is_array($meta)) continue;

        // Validate required fields and ID/folder match
        $id = $meta['id'] ?? '';
        if ($id !== $entry || !preg_match('/^[a-z0-9\-]+$/', $id)) continue;
        foreach (['id', 'name', 'version', 'author'] as $field) {
            if (empty($meta[$field])) continue 2;
        }

        $result[] = [
            'id'          => $id,
            'name'        => $meta['name'],
            'version'     => $meta['version'],
            'author'      => $meta['author'],
            'description' => $meta['description'] ?? '',
            'url'         => $meta['url'] ?? '',
            'enabled'     => (bool) ($states[$id]['enabled'] ?? false),
        ];
    }
}

// Sort alphabetically by name
usort($result, fn($a, $b) => strcmp($a['name'], $b['name']));

$response['success']    = true;
$response['extensions'] = $result;
