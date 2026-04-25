<?php
/**
 * PureWiki - Internationalization (i18n)
 *
 * Provides translation functions for the admin dashboard.
 * Loads language files from /purewiki/lang/ and exposes helper
 * functions for both PHP views and JavaScript injection.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

require_once __DIR__ . '/config.php';

/**
 * Returns the currently configured dashboard language code.
 */
function getDashboardLanguage(): string {
    if (isset($GLOBALS['PW_DASHBOARD_LANG'])) {
        return $GLOBALS['PW_DASHBOARD_LANG'];
    }
    $config = getGlobalConfig();
    return $config['dashboard_language'] ?? 'en';
}

/**
 * Loads and caches a language file from /purewiki/lang/.
 * If back to English if the requested language file does not exist.
 *
 * @param string|null $lang Language code (e.g. 'en', 'de'). Defaults to configured language.
 * @return array Flat or nested associative array of translations.
 */
function loadLanguage(?string $lang = null): array {
    static $cache = [];
    static $extCache = [];

    $lang = $lang ?? getDashboardLanguage();

    if (!isset($cache[$lang])) {
        $langDir = realpath(__DIR__ . '/../lang') ?: __DIR__ . '/../lang';
        $file = $langDir . '/' . $lang . '.json';

        // Fallback to English if requested language file missing
        if (!file_exists($file)) {
            $file = $langDir . '/en.json';
        }

        if (!file_exists($file)) {
            $cache[$lang] = [];
        } else {
            $data = readJson($file, []);
            $cache[$lang] = is_array($data) ? $data : [];
        }
        $extCache[$lang] = [];
    }

    // Merge extension translations
    if (isset($GLOBALS['PW_EXT_TRANSLATIONS'])) {
        foreach ($GLOBALS['PW_EXT_TRANSLATIONS'] as $extId => $extData) {
            if (!isset($extCache[$lang][$extId])) {
                if (!isset($cache[$lang]['ext'])) {
                    $cache[$lang]['ext'] = [];
                }
                $cache[$lang]['ext'][$extId] = $extData;
                $extCache[$lang][$extId] = true;
            }
        }
    }

    return $cache[$lang];
}

/**
 * Loads extension translations
 * Translation Structure: ext.<extensionId>.<key>
 *
 * @param string $extensionId The ID of the extension.
 * @param string $langDir Path to the extensions lang directory.
 */
function loadExtensionTranslations(string $extensionId, string $langDir): void {
    $lang = getDashboardLanguage();
    $file = $langDir . '/' . $lang . '.json';

    // Fallback to English
    if (!file_exists($file)) {
        $file = $langDir . '/en.json';
    }

    if (file_exists($file)) {
        $data = readJson($file, []);
        if (is_array($data)) {
            if (!isset($GLOBALS['PW_EXT_TRANSLATIONS'])) {
                $GLOBALS['PW_EXT_TRANSLATIONS'] = [];
            }
            $GLOBALS['PW_EXT_TRANSLATIONS'][$extensionId] = $data;
        }
    }
}

/**
 * Retrieves a translated string using dot-notation keys.
 * Supports sprintf-style placeholders (%s, %d).
 *
 * Usage: __('settings.general.title') or __('common.items_count', '5')
 *
 * @param string $key Dot-notation translation key.
 * @param mixed  ...$args Optional sprintf replacement values.
 * @return string Translated string, or the key itself if not found.
 */
function __($key, ...$args) {
    $translations = loadLanguage();
    $parts = explode('.', $key);
    $value = $translations;

    foreach ($parts as $part) {
        if (is_array($value) && isset($value[$part])) {
            $value = $value[$part];
        } else {
            return $key; // Key not found, return key as fallback
        }
    }

    if (!is_string($value)) {
        return $key;
    }

    if (!empty($args)) {
        return sprintf($value, ...$args);
    }

    return $value;
}

/**
 * Generates a <script> tag that exposes the full language catalog
 * as window.pwLang for client-side JavaScript access.
 *
 * @return string HTML script tag.
 */
function getLanguageScript(): string {
    $translations = loadLanguage();
    $json = json_encode($translations, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return '<script>window.pwLang = ' . $json . ';</script>';
}
