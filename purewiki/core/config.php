<?php
/**
 * PureWiki - Configuration Management
 *
 * Functions for reading and writing the global configuration
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

require_once __DIR__ . '/json.php';
require_once __DIR__ . '/fs.php';

/** Returns the currently configured dashboard theme. */
function getDashboardTheme(): string {
    $config = getGlobalConfig();
    return $config['dashboard_theme'] ?? 'dark';
}

/** Retrieves the global configuration from /config/config.json with default fallback. */
function getGlobalConfig(bool $forceReload = false): array {
    static $cache = null;
    if ($cache !== null && !$forceReload) return $cache;

    $defaultConfig = [
        'wiki_name'     => 'PureWiki',
        'wiki_description'=> '',
        'wiki_logo'     => '',
        'wiki_favicon'  => '',
        'current_theme' => 'default',
        'enable_cache'  => true,
        'cache_lifetime'=> '86400',
        'enable_admin_menu' => true,
        'custom_css' => '',
        'custom_html_head' => '',
        'custom_html_footer' => '',
        'custom_js_head' => '',
        'custom_js_footer' => '',
        'seo_prevent_indexing' => false,
        'seo_title_format' => '{{ page_title }} - {{ wiki_name }}',
        'seo_auto_canonical' => true,
        'seo_auto_opengraph' => true,
        'seo_twitter_cards' => true,
        'seo_schema_org' => true,
        'seo_og_image_url' => '',
        'seo_enable_sitemap' => true,
        'search_max_results' => 10,
        'enable_history' => true,
        'history_max_versions' => 20,
        'dashboard_language' => 'en',
        'dashboard_theme' => 'dark',
        'editor_show_raw' => true,
        'editor_show_markdown' => true,
        'editor_show_liveMarkdown' => true,
        'editor_show_code' => true,
        'editor_show_table' => true,
        'editor_show_pagelist' => true,
        'editor_show_toc' => true,
        'editor_show_callout' => true,
        'editor_show_block' => true,
        'editor_show_pageinclude' => true,
        'editor_show_snippet' => true,
        'editor_show_accordion' => true,
        'editor_show_grid' => true,
        'editor_show_math' => true,
        'dev_debug_output'        => false,
        'dev_debug_log_max_size'  => 2,
        'dev_debug_log_max_files' => 3,
        'allowed_file_extensions' => 'jpg, jpeg, png, gif, svg, webp, pdf, mp4, webm, zip, csv, txt',
        'allow_prerelease_updates' => false,
        'i18n_enabled'            => false,
        'i18n_default_lang'       => 'de',
        'i18n_supported_langs'    => [],
        'comments_enabled'        => false,
        'comments_require_approval' => true,
        'comments_spam_regex'     => [],
        'extensions'              => []
    ];

    try {
        $configFile = getConfigDir() . '/config.json';
        $data = readJsonFile($configFile);
        // Merge over defaults so new keys introduced in updates don't crash legacy installs
        $cache = is_array($data) ? array_merge($defaultConfig, $data) : $defaultConfig;
    } catch (PureWikiException $e) {
        $cache = $defaultConfig;
    }

    return $cache;
}

/**
 * Saves the global configuration to /config/config.json.
 * Creates the directory if it doesn't exist.
 */
function saveGlobalConfig(array $data): bool {
    $configDir = getConfigDir();
    if (!file_exists($configDir)) {
        require_once __DIR__ . '/fs.php';
        createDirectory($configDir);
    }

    // Filter out mail config keys, to prevent them from landing in config.json
    foreach ($data as $key => $val) {
        if (str_starts_with($key, 'mail_') && $key !== 'mail_encryption_key') {
            unset($data[$key]);
        }
    }

    // Merge with current config to prevent dropping other keys
    $current = getGlobalConfig();
    $merged = array_merge($current, $data);

    $result = writeJsonFile($configDir . '/config.json', $merged);

    // Update the static cache in case any new dynamic keys were added
    getGlobalConfig(true);

    return $result;
}

/** Checks if the initial setup has been completed. */
function isSetupCompleted(): bool {
    static $status = null;
    if ($status !== null) return $status;

    $configDir = getConfigDir();
    $configPath = $configDir . '/config.json';
    $usersPath = $configDir . '/users.json';

    // A valid installation needs both files; if one is missing, it's half-baked
    if (!file_exists($configPath) || !file_exists($usersPath)) {
        return $status = false;
    }

    $config = getGlobalConfig();
    $setupFlag = !empty($config['setup_completed']) && $config['setup_completed'] === true;

    // Sanity check: even if setup is flagged complete, an empty user file means nobody can log in
    $hasUsers = false;
    try {
        $users = readJsonFile($usersPath);
        $hasUsers = is_array($users) && !empty($users);
    } catch (Exception $e) {
        // If users.json is non-existent or invalid, it's not a complete setup
    }

    return $status = ($setupFlag && $hasUsers);
}

/**
 * Returns the per-extension settings stored in config/extensions/<id>.json.
 * Returns an empty array if the file does not exist yet.
 * 
 * @param string $id Extension ID
 */
function getExtensionSettings(string $id): array {
    $path = getConfigDir() . '/extensions/' . $id . '.json';
    if (!file_exists($path)) return [];
    try {
        $data = readJsonFile($path);
        return is_array($data) ? $data : [];
    } catch (PureWikiException $e) {
        return [];
    }
}

/**
 * Persists per-extension settings to config/extensions/<id>.json.
 * @param string $id   Extension ID
 * @param array  $data Settings to store
 */
function saveExtensionSettings(string $id, array $data): bool {
    $dir = getConfigDir() . '/extensions';
    if (!is_dir($dir)) createDirectory($dir);
    return writeJsonFile($dir . '/' . $id . '.json', $data);
}

/**
 * Enable/disable an extension by updating the 'extensions' key in config.json.
 * @param string $id      Extension ID.
 * @param bool   $enabled True to enable, false to disable.
 */
function setExtensionEnabled(string $id, bool $enabled): bool {
    $config  = getGlobalConfig();
    $states  = $config['extensions'] ?? [];

    if (!isset($states[$id]) || !is_array($states[$id])) {
        $states[$id] = [];
    }
    $states[$id]['enabled'] = $enabled;

    return saveGlobalConfig(['extensions' => $states]);
}
