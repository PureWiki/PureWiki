<?php
/**
 * PureWiki - Frontend Macro: Search
 *
 * Renders the search toggle button and dropdown for the frontend header.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');
?>
<li class="pw-search-wrapper">
    <button class="pw-search-toggle" id="pw-search-toggle" aria-label="Search">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
    </button>
    <div class="pw-search-dropdown" id="pw-search-dropdown">
        <input type="text" id="pw-search-input" placeholder="Search…" aria-label="Search" autocomplete="off">
        <div class="pw-search-results" id="pw-search-results"></div>
    </div>
</li>
