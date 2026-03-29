<?php
/**
 * PureWiki - Sidebar Toggle Macro
 *
 * Renders the sidebar toggle button only if the left sidebar is not hidden.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

$hideLeftSidebar = $pageData['Settings']['hide_left_sidebar'] ?? false;

if (!$hideLeftSidebar): ?>
    <button class="pw-sidebarmenu" id="pw-sidebarmenu" aria-label="Toggle sidebar">
        <span></span><span></span><span></span>
    </button>
<?php endif; ?>
