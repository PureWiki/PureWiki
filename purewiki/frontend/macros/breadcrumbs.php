<?php
/**
 * PureWiki - Frontend Macro: Breadcrumbs
 *
 * Renders the breadcrumb navigation based on the current context path.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

// $contextPath is available via extract() in renderer.php
$path = $contextPath ?? '/';
$parts = array_filter(explode('/', $path));
if (!function_exists('getCachedPagesTree')) {
    require_once __DIR__ . '/../../core/tree.php';
}
require_once __DIR__ . '/../../core/fs.php';
require_once __DIR__ . '/../../core/misc.php';

$lang     = defined('CURRENT_LANG') ? CURRENT_LANG : '';
$pagesDir = getPageDir();
$langPrefix = $lang !== '' ? '/' . $lang : '';

echo '<nav aria-label="breadcrumb">';
echo '<ul>';

// Home link
$showHome = true;
if (isset($config) && isset($config['breadcrumbs_show_start_page'])) {
    $showHome = (bool) $config['breadcrumbs_show_start_page'];
}

if ($showHome) {
    $homeLabel = 'Home';
    if ($lang !== '' && function_exists('getPageFilename')) {
        $homeLangFile    = $pagesDir . '/' . getPageFilename($lang);
        $homeDefaultFile = $pagesDir . '/page.json';
        $homeData = null;
        if (file_exists($homeLangFile)) {
            $homeData = readJson($homeLangFile, null);
        } elseif (file_exists($homeDefaultFile)) {
            $homeData = readJson($homeDefaultFile, null);
        }
        if (!empty($homeData['pagetitle'])) {
            $homeLabel = $homeData['pagetitle'];
        }
    }
    echo '<li><a href="' . htmlspecialchars(BASE_PATH . $langPrefix . '/') . '">' . htmlspecialchars($homeLabel) . '</a></li>';
}

$currentUrl = '';

$tree = getCachedPagesTree($pagesDir, '', $lang);
$currentLevel = $tree;

foreach ($parts as $part) {
    $currentUrl .= '/' . $part;

    // Attempt to get the page title for this path
    $title = prepareTitle($part);

    // Find the title using the cached page tree to prevent N+1 file I/O operations
    $found = false;
    foreach ($currentLevel as $node) {
        if (basename($node['path']) === $part) {
            $title = $node['name'];
            $currentLevel = $node['children'] ?? [];
            $found = true;
            break;
        }
    }

    // Reset level if path part was not found to prevent incorrect matching of siblings
    if (!$found) {
        $currentLevel = [];
    }

    echo '<li><a href="' . htmlspecialchars(BASE_PATH . $langPrefix . $currentUrl) . '">' . htmlspecialchars($title) . '</a></li>';
}

echo '</ul>';
echo '</nav>';
