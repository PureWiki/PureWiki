<?php
/**
 * PureWiki - Dynamic Sitemap Generator
 *
 * Generates an XML sitemap of all public pages.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/tree.php';
require_once __DIR__ . '/../core/fs.php';

// Prepare base URL
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$baseUrl = $protocol . $host;

// Get all public pages
$rootDir = getPageDir();
$tree = getCachedPagesTree($rootDir, '', '');

$config = getGlobalConfig();
$i18nEnabled = !empty($config['i18n_enabled']);
$supportedLangs = [];
$defaultLang = '';
if ($i18nEnabled) {
    require_once __DIR__ . '/../core/i18n_pages.php';
    $supportedLangs = getSupportedPageLangs();
    $defaultLang = getDefaultPageLang();
}

// Start XML output
header('Content-Type: application/xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
$xhtmlNs = ($i18nEnabled && !empty($supportedLangs)) ? ' xmlns:xhtml="http://www.w3.org/1999/xhtml"' : '';
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . $xhtmlNs . '>' . PHP_EOL;

/**
 * Builds hreflang alternate link tags for a given page path.
 * Only includes languages for translated pages.
 *
 * @param string $baseUrl       Absolute base URL of the wiki
 * @param string $path          Page path
 * @param bool   $i18nEnabled   Whether i18n is active
 * @param array  $supportedLangs Non-default languages configured in the wiki
 * @param string $defaultLang   Default language code
 * @param string $pagesDir      Absolute path to the pages directory for existence checks
 */
function getSitemapAlternates(string $baseUrl, string $path, bool $i18nEnabled, array $supportedLangs, string $defaultLang, string $pagesDir = ''): string {
    $alternates = '';
    if (!$i18nEnabled || empty($supportedLangs)) return '';

    $canonicalUrl = $baseUrl . ($path === '' || $path === '/' ? '/' : $path);
    $alternates .= '    <xhtml:link rel="alternate" hreflang="x-default" href="' . htmlspecialchars($canonicalUrl) . '" />' . PHP_EOL;
    $alternates .= '    <xhtml:link rel="alternate" hreflang="' . htmlspecialchars($defaultLang) . '" href="' . htmlspecialchars($canonicalUrl) . '" />' . PHP_EOL;

    foreach ($supportedLangs as $lang) {
        // Only add if translation exists
        if ($pagesDir !== '') {
            $pageSubPath = ($path === '/' || $path === '') ? '' : trim($path, '/') . '/';
            $translationFile = rtrim($pagesDir, '/') . '/' . $pageSubPath . 'page.' . $lang . '.json';
            if (!file_exists($translationFile)) {
                continue; // Skip page if translation does not exist
            }
        }
        $langUrl = $baseUrl . '/' . $lang . ($path === '/' || $path === '' ? '' : $path);
        $alternates .= '    <xhtml:link rel="alternate" hreflang="' . htmlspecialchars($lang) . '" href="' . htmlspecialchars($langUrl) . '" />' . PHP_EOL;
    }

    return $alternates;
}

// Add Startpage
$startpageJson = rtrim($rootDir, '/') . '/page.json';
$isStartpagePublic = true;
if (file_exists($startpageJson)) {
    $data = readJson($startpageJson, null);
    if (!empty($data['isPrivate'])) {
        $isStartpagePublic = false;
    }
}

if ($isStartpagePublic) {
    $alternates = getSitemapAlternates($baseUrl, '/', $i18nEnabled, $supportedLangs, $defaultLang, $rootDir);

    echo '  <url>' . PHP_EOL;
    echo '    <loc>' . htmlspecialchars($baseUrl . '/') . '</loc>' . PHP_EOL;
    echo $alternates;
    echo '    <changefreq>daily</changefreq>' . PHP_EOL;
    echo '    <priority>1.0</priority>' . PHP_EOL;
    echo '  </url>' . PHP_EOL;

    if ($i18nEnabled && !empty($supportedLangs)) {
        foreach ($supportedLangs as $lang) {
            // Only add language startpage if translation exists
            $startpageLangJson = rtrim($rootDir, '/') . '/page.' . $lang . '.json';
            if (!file_exists($startpageLangJson)) continue;

            $langUrl = $baseUrl . '/' . $lang;
            echo '  <url>' . PHP_EOL;
            echo '    <loc>' . htmlspecialchars($langUrl . '/') . '</loc>' . PHP_EOL;
            echo $alternates;
            echo '    <changefreq>daily</changefreq>' . PHP_EOL;
            echo '    <priority>1.0</priority>' . PHP_EOL;
            echo '  </url>' . PHP_EOL;
        }
    }
}

/**
 * Add pages from the tree to the sitemap
 * @param array  $nodes         Array of page nodes
 * @param string $baseUrl       Base URL of the wiki
 * @param bool   $i18nEnabled   Whether i18n is active
 * @param array  $supportedLangs Non-default languages configured in the wiki
 * @param string $defaultLang   Default language code
 * @param string $pagesDir      Absolute path to the pages directory for existence checks
 * @return void
 */
function renderSitemapNodes(array $nodes, string $baseUrl, bool $i18nEnabled, array $supportedLangs, string $defaultLang, string $pagesDir): void {
    foreach ($nodes as $node) {
        $path = '/' . trim($node['path'], '/');

        $alternates = getSitemapAlternates($baseUrl, $path, $i18nEnabled, $supportedLangs, $defaultLang, $pagesDir);
        $depth      = count(explode('/', trim($node['path'], '/')));
        $priority   = max(0.5, 1.0 - ($depth * 0.1));

        // Default language entry
        echo '  <url>' . PHP_EOL;
        echo '    <loc>' . htmlspecialchars($baseUrl . $path) . '</loc>' . PHP_EOL;
        echo $alternates;
        echo '    <changefreq>weekly</changefreq>' . PHP_EOL;
        echo '    <priority>' . number_format($priority, 1) . '</priority>' . PHP_EOL;
        echo '  </url>' . PHP_EOL;

        if ($i18nEnabled && !empty($supportedLangs)) {
            foreach ($supportedLangs as $lang) {
                $pageSubPath     = trim($node['path'], '/') . '/';
                $translationFile = rtrim($pagesDir, '/') . '/' . $pageSubPath . 'page.' . $lang . '.json';
                if (!file_exists($translationFile)) continue;

                $langUrl = $baseUrl . '/' . $lang . $path;
                echo '  <url>' . PHP_EOL;
                echo '    <loc>' . htmlspecialchars($langUrl) . '</loc>' . PHP_EOL;
                echo $alternates;
                echo '    <changefreq>weekly</changefreq>' . PHP_EOL;
                echo '    <priority>' . number_format($priority, 1) . '</priority>' . PHP_EOL;
                echo '  </url>' . PHP_EOL;
            }
        }

        if (!empty($node['children'])) {
            renderSitemapNodes($node['children'], $baseUrl, $i18nEnabled, $supportedLangs, $defaultLang, $pagesDir);
        }
    }
}

renderSitemapNodes($tree, $baseUrl, $i18nEnabled, $supportedLangs, $defaultLang, $rootDir);

echo '</urlset>' . PHP_EOL;
