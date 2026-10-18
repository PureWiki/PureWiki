<?php
/**
 * PureWiki - System Configuration
 *
 * Manages global wiki settings. Handles retrieving and updating the
 * system config file and rebuilding dependent caches.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

if ($action === 'get_config') {
    $response['success'] = true;
    $response['message'] = 'OK';
    $response['data'] = getGlobalConfig();

} else if ($action === 'save_config') {
    $configDataRaw = $_POST['config'] ?? '{}';
    $configData = json_decode($configDataRaw, true);
    if (is_array($configData)) {
        if (array_key_exists('theme_colors', $configData)) {
            require_once __DIR__ . '/../../core/theme.php';
            $theme = (string) ($configData['current_theme'] ?? getGlobalConfig()['current_theme']);
            $configData['theme_colors'] = sanitizeThemeColors($configData['theme_colors'], getThemeColorDefaults($theme));
        }
        saveGlobalConfig($configData);
        $response['success'] = true;
        $response['message'] = 'Settings saved successfully.';
        logActivity('settings_update', 'system');
        clearCache();
    } else {
        $response['message'] = 'Invalid config data.';
    }
}
