<?php
/**
 * PureWiki - List Snippets Action
 *
 * Returns a list of all snippets for the dashboard sidebar and editor plugin.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

if ($action === 'list_snippets') {
    require_once __DIR__ . '/../../core/snippets.php';
    $snippetsDir = getSnippetsDir();
    $results = getSnippetsList($snippetsDir);

    $response['success'] = true;
    $response['snippets'] = $results;
}