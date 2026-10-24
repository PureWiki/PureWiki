<?php
/**
 * PureWiki Theme - Default Page Structure
 *
 * Main layout of the theme. Combines header, content and footer elements.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

// Document start, <head> and site header
echo file_get_contents(__DIR__ . '/elements/header.php');

echo '<main class="pw-container pw-main-container">';

// Left sidebar: desktop tree and mobile menu share one container
echo '{{ if show_left_sidebar }}';
echo '<aside class="pw-virtual-sidebar pw-sidebar-left" id="pw-sidebar-left">';
echo '<div class="pw-sidebar-desktop-only">{{ virtual:left_sidebar }}</div>';
echo '<div class="pw-sidebar-mobile-only">{{ virtual:mobile_sidebar }}</div>';
echo '</aside>';
echo '{{ endif }}';

// Page content with breadcrumbs above and prev/next navigation and comments below
echo '<div class="pw-main-content">';
echo '{{ macro:breadcrumbs }}';
echo file_get_contents(__DIR__ . '/elements/content.php');
echo '{{ macro:tags }}';
echo '{{ macro:prevnext }}';
echo '{{ macro:comments }}';
echo '</div>';

// Right sidebar
echo '{{ if show_right_sidebar }}';
echo '<aside class="pw-virtual-sidebar pw-sidebar-right">';
echo '{{ virtual:right_sidebar }}';
echo '</aside>';
echo '{{ endif }}';

// Dims the page while the mobile sidebar is open
echo '<div class="pw-sidebar-backdrop" id="pw-sidebar-backdrop"></div>';
echo '</main>';

echo '<div class="pw-container pw-lang-switcher-container">{{ macro:lang_switcher }}</div>';
echo '{{ virtual:_footer }}';

// Footer, to-top button and closing tags
echo file_get_contents(__DIR__ . '/elements/footer.php');
