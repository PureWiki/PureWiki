<?php
/**
 * PureWiki - Page Backlinks API
 *
 * Get incoming backlinks for a page.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

require_once __DIR__ . '/../../core/backlinks.php';

$path = $_POST['path'] ?? $_GET['path'] ?? '';
if ($path === '') {
    $path = '/';
}

$enabled = isBacklinksEnabled();
$backlinks = $enabled ? getPageBacklinks($path) : [];

$response['success'] = true;
$response['enabled'] = $enabled;
$response['path'] = $path;
$response['backlinks'] = $backlinks;
$response['count'] = count($backlinks);