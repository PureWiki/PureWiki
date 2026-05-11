<?php
/**
 * PureWiki - i18n Pages Core Module
 *
 * Helper functions for handling multilingual page content.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

require_once __DIR__ . '/config.php';

/** Get configured supported languages */
function getSupportedPageLangs(): array {
    $config = getGlobalConfig();
    if (empty($config['i18n_enabled'])) {
        return [];
    }
    return $config['i18n_supported_langs'] ?? [];
}

/** Get configured default language */
function getDefaultPageLang(): string {
    $config = getGlobalConfig();
    return $config['i18n_default_lang'] ?? 'en';
}

/** Detect language prefix from URL path */
function detectLangFromPath(string $path): string {
    $config = getGlobalConfig();
    if (empty($config['i18n_enabled'])) {
        return '';
    }

    $supported = getSupportedPageLangs();
    $defaultLang = getDefaultPageLang();
    $validPrefixes = array_merge([$defaultLang], $supported);
    
    if (empty($validPrefixes)) {
        return '';
    }

    $cleanPath = trim($path, '/');
    if ($cleanPath === '') return '';
    $parts = explode('/', $cleanPath);

    if (in_array($parts[0], $validPrefixes, true)) {
        return $parts[0];
    }

    return '';
}

/** Strip language prefix from URL path */
function stripLangPrefix(string $path, string $lang): string {
    if (empty($lang)) {
        return $path;
    }

    $prefix = '/' . $lang;
    
    if ($path === $prefix) {
        return '/';
    }

    if (str_starts_with($path, $prefix . '/')) {
        return substr($path, strlen($prefix));
    }

    return $path;
}

/** Resolve filename for a specific language */
function getPageFilename(string $lang, bool $isDraft = false): string {
    if (empty($lang)) {
        return $isDraft ? 'page.draft.json' : 'page.json';
    }
    return $isDraft ? 'page.draft.' . $lang . '.json' : 'page.' . $lang . '.json';
}

/** Check if page translation exists */
function pageTranslationExists(string $pageDir, string $lang): bool {
    $publishedPath = rtrim($pageDir, '/') . '/' . getPageFilename($lang, false);
    $draftPath = rtrim($pageDir, '/') . '/' . getPageFilename($lang, true);
    
    return file_exists($publishedPath) || file_exists($draftPath);
}
