<?php
/**
 * PureWiki - Frontend Macro: PrevNext
 *
 * Renders Next and Previous navigation buttons based on page configuration.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

// $config (page settings) and $contextPath are available via extract() in renderer.php or global scope
$path = $contextPath ?? '/';
$settings = $pageData['Settings'] ?? [];


$enabled = isset($settings['prevnext_enabled']) ? (bool)$settings['prevnext_enabled'] : false;

if (!$enabled) {
    return;
}

$scope = $settings['prevnext_scope'] ?? 'siblings';
$includeHigherLevels = ($scope === 'hierarchy');

require_once __DIR__ . '/../../core/tree.php';
require_once __DIR__ . '/../../core/i18n.php';

$neighbors = getPageNeighbors($path, $includeHigherLevels);

if (!$neighbors['prev'] && !$neighbors['next']) {
    return;
}

echo '<nav class="pw-prevnext-nav" aria-label="Page navigation">';

// Previous Button
if ($neighbors['prev']) {
    $prevPath = '/' . trim($neighbors['prev']['path'], '/');
    $prevName = $neighbors['prev']['name'];
    echo '<a href="' . htmlspecialchars(BASE_PATH . $prevPath) . '" class="pw-prevnext-btn pw-prev">';
    echo '<div class="pw-prevnext-label"><iconify-icon icon="mdi:chevron-left"></iconify-icon> ' . __('editor.prev') . '</div>';
    echo '<div class="pw-prevnext-title">' . htmlspecialchars($prevName) . '</div>';
    echo '</a>';
} else {
    // Spacer for layout consistency if next exists
    echo '<div class="pw-prevnext-spacer"></div>';
}

// Next Button
if ($neighbors['next']) {
    $nextPath = '/' . trim($neighbors['next']['path'], '/');
    $nextName = $neighbors['next']['name'];
    echo '<a href="' . htmlspecialchars(BASE_PATH . $nextPath) . '" class="pw-prevnext-btn pw-next">';
    echo '<div class="pw-prevnext-label">' . __('editor.next') . ' <iconify-icon icon="mdi:chevron-right"></iconify-icon></div>';
    echo '<div class="pw-prevnext-title">' . htmlspecialchars($nextName) . '</div>';
    echo '</a>';
} else {
    // Spacer
    echo '<div class="pw-prevnext-spacer"></div>';
}

echo '</nav>';
