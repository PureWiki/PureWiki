<?php
/**
 * PureWiki - Cache Management
 *
 * Handles clearing and status of the system cache. Triggers the removal
 * of all cached HTML fragments for the frontend.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

if ($action === 'clear_cache') {
    clearCache();
    $response['success'] = true;
    $response['message'] = 'Cache cleared.';
}
