<?php
/**
 * PureWiki - API: toggle_extension
 *
 * Enables or disables an extension by its ID.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

$id      = trim($_POST['extension_id'] ?? '');
$enabled = filter_var($_POST['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);

if (empty($id) || !preg_match('/^[a-z0-9\-]+$/', $id)) {
    throw new PureWikiException('Invalid or missing extension_id.');
}

// Verify the extension exists and has a valid meta.json
$extDir  = getExtensionsDir() . DIRECTORY_SEPARATOR . $id;
$metaFile = $extDir . DIRECTORY_SEPARATOR . 'meta.json';
if (!is_dir($extDir) || !file_exists($metaFile)) {
    throw new PureWikiException('Extension not found: ' . $id);
}

if (!setExtensionEnabled($id, $enabled)) {
    throw new PureWikiException('Failed to save extension state.');
}

// Invalidate cache so extension changes are reflected in pages
require_once __DIR__ . '/../../core/cache.php';
clearAllCache();

$response['success'] = true;
$response['enabled'] = $enabled;
