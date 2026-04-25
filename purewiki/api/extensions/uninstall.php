<?php
/**
 * PureWiki - API: uninstall_extension
 *
 * Deletes an extension folder from /extensions/ and
 * cleans up its entry from config.json and its config file
 *

 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

$id = trim($_POST['extension_id'] ?? '');

if (empty($id) || !preg_match('/^[a-z0-9\-]+$/', $id)) {
    throw new PureWikiException('Invalid or missing extension_id.');
}

$extDir = getExtensionsDir() . DIRECTORY_SEPARATOR . $id;

// Ensure the resolved path stays inside the extensions directory
$extensionsBase = realpath(getExtensionsDir());
$resolvedExtDir = realpath($extDir);
if ($resolvedExtDir === false || !str_starts_with($resolvedExtDir, $extensionsBase . DIRECTORY_SEPARATOR)) {
    throw new PureWikiException('Path traversal detected.');
}

if (!is_dir($resolvedExtDir)) {
    throw new PureWikiException('Extension not found: ' . $id);
}
// Delete the extension folder
deleteDirectory($resolvedExtDir);

// Remove the enabled/disabled entry from config.json
$config = getGlobalConfig();
$states = $config['extensions'] ?? [];
if (isset($states[$id])) {
    unset($states[$id]);
    saveGlobalConfig(['extensions' => $states]);
}

// Remove the config file if it exists
$extConfigFile = getConfigDir() . '/extensions/' . $id . '.json';
if (file_exists($extConfigFile)) {
    @unlink($extConfigFile);
}

$response['success'] = true;
