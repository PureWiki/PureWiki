<?php
/**
 * PureWiki - List All Comments API Action
 *
 * Lists all comments from all pages
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

require_once __DIR__ . '/../../core/comments.php';

try {
    $comments = getAllComments();
    $response['success'] = true;
    $response['data'] = $comments;
} catch (PureWikiException $exception) {
    $response['message'] = 'Failed to load global comments.';
}
