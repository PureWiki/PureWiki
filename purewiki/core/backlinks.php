<?php
/**
 * PureWiki - Backlinks
 *
 * Manages and gets page backlinks via cached reverse index.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

require_once __DIR__ . '/fs.php';
require_once __DIR__ . '/json.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/cache.php';
require_once __DIR__ . '/misc.php';
require_once __DIR__ . '/i18n_pages.php';

/**
 * Returns path to the backlinks cache file.
 */
function getBacklinksFilePath(): string {
    return getCacheDir() . '/backlinks.json';
}

/**
 * Checks if the backlinks are enabled in global settings.
 */
function isBacklinksEnabled(): bool {
    $config = getGlobalConfig();
    return !isset($config['enable_backlinks']) || !empty($config['enable_backlinks']);
}

/**
 * Normalizes an internal link URL or path.
 */
function normalizePageLink(string $href): ?string {
    $href = trim(htmlspecialchars_decode($href, ENT_QUOTES));
    if ($href === '' || $href[0] === '#') {
        return null;
    }

    // Absolute URLs with scheme
    if (str_contains($href, '://')) {
        $linkHost = strtolower(parse_url($href, PHP_URL_HOST) ?? '');
        $rawHost = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? '';
        if (str_contains($rawHost, ',')) {
            $rawHost = explode(',', $rawHost)[0];
        }
        $currentHost = strtolower(preg_replace('/:\d+$/', '', trim($rawHost)));
        if ($linkHost === '' || ($currentHost !== '' && $linkHost !== $currentHost && !in_array($linkHost, ['localhost', '127.0.0.1'], true))) {
            return null; // External domain
        }
        $path = parse_url($href, PHP_URL_PATH);
    } elseif (str_contains($href, ':')) {
        return null; // Non-HTTP schemes like mailto: or tel:
    } else {
        $path = parse_url($href, PHP_URL_PATH);
    }

    if (!$path) return null;

    if (defined('BASE_PATH') && BASE_PATH !== '' && str_starts_with($path, BASE_PATH)) {
        $path = substr($path, strlen(BASE_PATH));
    }

    $path = sanitizePath($path);
    if ($lang = detectLangFromPath($path)) {
        $path = stripLangPrefix($path, $lang);
    }

    if ($path === '' || $path === '/') {
        return '/';
    }

    // Allow only valid slugs and no dots or file extensions
    if (!preg_match('#^(/[a-zA-Z0-9_\-]+)+$#', $path)) {
        return null;
    }

    // Ensure path stays within pages directory
    $pagesDir = getPageDir();
    if (!isPathInDir($pagesDir . $path, $pagesDir)) {
        return null;
    }

    return $path;
}

/**
 * Extract page links from Editor.js blocks.
 */
function extractInternalLinksFromBlocks(array $blocks): array {
    $targets = [];
    _collectBlockLinks($blocks, $targets);
    return array_keys($targets);
}

/**
 * Collect page links from block arrays.
 */
function _collectBlockLinks(mixed $data, array &$targets): void {
    if (!is_array($data)) return;

    // Skip code and raw blocks
    if (isset($data['type']) && in_array($data['type'], ['code', 'raw', 'delimiter'], true)) {
        return;
    }

    static $keys = ['pagePath' => true, 'url' => true, 'href' => true, 'link' => true, 'targetPath' => true, 'page' => true];

    foreach ($data as $key => $val) {
        if (is_array($val)) {
            _collectBlockLinks($val, $targets);
        } elseif (is_string($val)) {
            if (isset($keys[$key])) {
                $clean = normalizePageLink($val);
                if ($clean !== null) $targets[$clean] = true;
            } elseif (str_contains($val, '<a') || str_contains($val, '](')) {
                if (preg_match_all('/<a\s+[^>]*?href=["\']([^"\']+)["\']/i', $val, $m)) {
                    foreach ($m[1] as $href) {
                        $clean = normalizePageLink($href);
                        if ($clean !== null) $targets[$clean] = true;
                    }
                }
                if (preg_match_all('/\[[^\]]*?\]\(([^)\s]+)\)/', $val, $m)) {
                    foreach ($m[1] as $url) {
                        $clean = normalizePageLink($url);
                        if ($clean !== null) $targets[$clean] = true;
                    }
                }
            }
        }
    }
}

/**
 * Builds the complete backlinks index
 */
function buildBacklinksIndex(): array {
    if (!isBacklinksEnabled()) {
        invalidateBacklinksCache();
        return [
            'generated'     => date('c'),
            'indexed_pages' => 0,
            'backlinks'     => []
        ];
    }

    $pagesDir = getPageDir();
    $config = getGlobalConfig();
    $langs = array_merge([''], (!empty($config['i18n_enabled']) && !empty($config['i18n_supported_langs'])) ? $config['i18n_supported_langs'] : []);

    $forward = [];
    $pageTitles = [];
    _collectBacklinksFromDir($pagesDir, $pagesDir, $langs, $forward, $pageTitles);

    $backlinks = [];
    foreach ($forward as $source => $targets) {
        $sourceTitle = $pageTitles[$source] ?? prepareTitle(basename($source === '/' ? 'Startseite' : $source));
        foreach ($targets as $target) {
            if ($source !== $target && isset($pageTitles[$target])) {
                $backlinks[$target][] = ['path' => $source, 'title' => $sourceTitle];
            }
        }
    }

    $index = [
        'generated'     => date('c'),
        'indexed_pages' => count($forward),
        'backlinks'     => $backlinks
    ];

    $cacheDir = getCacheDir();
    if (!is_dir($cacheDir)) {
        createDirectory($cacheDir);
    }
    writeJsonFile(getBacklinksFilePath(), $index);

    return $index;
}

/**
 * Traverses page directories to collect links
 */
function _collectBacklinksFromDir(string $dir, string $pagesDir, array $langs, array &$forward, array &$pageTitles): void {
    $normDir = str_replace('\\', '/', $dir);
    $normRoot = str_replace('\\', '/', $pagesDir);
    $rel = '/' . ltrim(substr($normDir, strlen($normRoot)), '/');

    $links = [];
    $title = '';

    foreach ($langs as $lang) {
        $file = $dir . DIRECTORY_SEPARATOR . ($lang === '' ? 'page.json' : "page.{$lang}.json");
        if (file_exists($file)) {
            $data = readJson($file, null);
            if (is_array($data)) {
                if ($title === '' && !empty($data['pagetitle'])) {
                    $title = $data['pagetitle'];
                }
                if (!empty($data['blocks']) && is_array($data['blocks'])) {
                    $targets = [];
                    _collectBlockLinks($data['blocks'], $targets);
                    foreach (array_keys($targets) as $t) {
                        $links[$t] = true;
                    }
                }
            }
        }
    }

    if (!empty($links) || $title !== '') {
        $forward[$rel] = array_keys($links);
        $pageTitles[$rel] = $title !== '' ? $title : prepareTitle(basename($rel === '/' ? 'Startseite' : $dir));
    }

    $items = scandir($dir);
    if ($items === false) return;

    foreach ($items as $item) {
        if ($item === '.' || $item === '..' || str_starts_with($item, '_')) continue;
        $full = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($full)) {
            _collectBacklinksFromDir($full, $pagesDir, $langs, $forward, $pageTitles);
        }
    }
}

/**
 * Returns backlinks index and rebuilding it if missing.
 */
function getBacklinksIndex(): array {
    $filePath = getBacklinksFilePath();
    if (file_exists($filePath)) {
        $index = readJson($filePath, null);
        if (is_array($index) && isset($index['backlinks'])) {
            return $index;
        }
    }
    return buildBacklinksIndex();
}

/**
 * Returns all backlinks for a given page.
 */
function getPageBacklinks(string $path): array {
    if (!isBacklinksEnabled()) {
        return [];
    }
    $index = getBacklinksIndex();
    return $index['backlinks']['/' . trim($path, '/')] ?? [];
}