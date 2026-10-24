<?php
/**
 * PureWiki - Frontend Macro: Tags
 *
 * Renders the tags of the current page as an HTML list.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

if (!empty($pageData['Tags']) && is_array($pageData['Tags'])) {
    echo renderTagsHtml($pageData['Tags'], $config);
}
