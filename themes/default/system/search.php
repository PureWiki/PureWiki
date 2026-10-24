<?php
/**
 * PureWiki Theme - Default Search Page
 *
 * Layout of the search page. Shows the search form of the search_page macro instead of page content.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

// Document start, <head> and site header
echo file_get_contents(__DIR__ . '/../elements/header.php');

echo '<main class="pw-container pw-main-container">';

// Left sidebar: desktop tree and mobile menu share one container
echo '<aside class="pw-virtual-sidebar pw-sidebar-left" id="pw-sidebar-left">';
echo '<div class="pw-sidebar-desktop-only">{{ virtual:left_sidebar }}</div>';
echo '<div class="pw-sidebar-mobile-only">{{ virtual:mobile_sidebar }}</div>';
echo '</aside>';

echo '<div class="pw-main-content">';
echo '<h1>{{ page_title }}</h1>';
echo '{{ macro:search_page }}';
echo '</div>';

// Dims the page while the mobile sidebar is open
echo '<div class="pw-sidebar-backdrop" id="pw-sidebar-backdrop"></div>';
echo '</main>';

echo '<div class="pw-container pw-lang-switcher-container">{{ macro:lang_switcher }}</div>';
echo '{{ virtual:_footer }}';

// Footer, to-top button and closing tags
echo file_get_contents(__DIR__ . '/../elements/footer.php');
