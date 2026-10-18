<?php
/**
 * PureWiki - Theme Colors
 *
 * Reads the adjustable colors from a themes theme.json and builds the
 * CSS overrides from the values saved in the global settings.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

require_once __DIR__ . '/json.php';
require_once __DIR__ . '/fs.php';

/**
 * Returns adjustable colors of a theme, keyed by CSS variable.
 * Empty if theme has no valid theme.json.
 */
function getThemeColorDefaults(string $theme): array {
    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $theme)) return [];

    $manifest = readJson(getThemesDir() . '/' . $theme . '/theme.json', []);
    $colors = is_array($manifest) ? ($manifest['colors'] ?? []) : [];
    if (!is_array($colors)) return [];

    $valid = [];
    foreach ($colors as $var => $def) {
        if (!is_string($var) || !preg_match('/^--[a-z0-9-]+$/', $var) || !is_array($def)) continue;
        if (!isValidThemeColor($def['light'] ?? null) || !isValidThemeColor($def['dark'] ?? null)) continue;
        $valid[$var] = [
            'label' => (string) ($def['label'] ?? $var),
            'light' => strtolower($def['light']),
            'dark'  => strtolower($def['dark']),
        ];
    }
    return $valid;
}

/** 
 * Checks for a 6-digit hex color 
 */
function isValidThemeColor($value): bool {
    return is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1;
}

/**
 * Keeps only known variables with valid hex values that differ from the theme defaults.
 */
function sanitizeThemeColors($input, array $defaults): array {
    if (!is_array($input)) return [];

    $clean = [];
    foreach ($defaults as $var => $def) {
        $pair = $input[$var] ?? null;
        if (!is_array($pair)) continue;

        $light = isValidThemeColor($pair['light'] ?? null) ? strtolower($pair['light']) : $def['light'];
        $dark  = isValidThemeColor($pair['dark'] ?? null) ? strtolower($pair['dark']) : $def['dark'];

        if ($light !== $def['light'] || $dark !== $def['dark']) {
            $clean[$var] = ['light' => $light, 'dark' => $dark];
        }
    }
    return $clean;
}

/** 
 * Builds the inline style block with color overrides or an empty string. 
 */
function buildThemeColorsCss(array $config, string $theme): string {
    $colors = sanitizeThemeColors($config['theme_colors'] ?? [], getThemeColorDefaults($theme));
    if (empty($colors)) return '';

    $rules = '';
    foreach ($colors as $var => $pair) {
        $rules .= $var . ':light-dark(' . $pair['light'] . ',' . $pair['dark'] . ');';
    }
    return '<style>:root{' . $rules . '}</style>';
}
