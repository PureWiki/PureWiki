<?php
/**
 * PureWiki - API: get_extension_info
 *
 * Returns full meta.json data and per-extension config for a single extension.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

$id = trim($_POST['extension_id'] ?? $_GET['extension_id'] ?? '');

if (empty($id) || !preg_match('/^[a-z0-9\-]+$/', $id)) {
    throw new PureWikiException('Invalid or missing extension_id.');
}

$extDir   = getExtensionsDir() . DIRECTORY_SEPARATOR . $id;
$metaFile = $extDir . DIRECTORY_SEPARATOR . 'meta.json';

if (!is_dir($extDir) || !file_exists($metaFile)) {
    throw new PureWikiException('Extension not found: ' . $id);
}

$raw  = file_get_contents($metaFile);
$meta = $raw !== false ? json_decode($raw, true) : null;
if (!is_array($meta)) {
    throw new PureWikiException('Invalid meta.json for extension: ' . $id);
}

$config = getGlobalConfig();
$states = $config['extensions'] ?? [];

$response['success']   = true;
$response['meta']      = $meta;
$response['enabled']   = (bool) ($states[$id]['enabled'] ?? false);
$response['settings']  = getExtensionSettings($id);
