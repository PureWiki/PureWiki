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
// Absolute base URL for the wiki root
$baseUrl = $protocol . $host;

// Get all public pages
$rootDir = getPageDir();
$tree = getCachedPagesTree($rootDir);

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
// Only add xhtml namespace when needed – otherwise browsers can't apply their built-in sitemap stylesheet
$xhtmlNs = ($i18nEnabled && !empty($supportedLangs)) ? ' xmlns:xhtml="http://www.w3.org/1999/xhtml"' : '';
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . $xhtmlNs . '>' . PHP_EOL;

function getSitemapAlternates($baseUrl, $path, $i18nEnabled, $supportedLangs, $defaultLang) {
    $alternates = '';
    if ($i18nEnabled && !empty($supportedLangs)) {
        $xDefaultUrl = $baseUrl . $path;
        if ($xDefaultUrl === '') $xDefaultUrl = '/';
        $alternates .= '    <xhtml:link rel="alternate" hreflang="x-default" href="' . htmlspecialchars($xDefaultUrl) . '" />' . PHP_EOL;
        $alternates .= '    <xhtml:link rel="alternate" hreflang="' . htmlspecialchars($defaultLang) . '" href="' . htmlspecialchars($xDefaultUrl) . '" />' . PHP_EOL;
        foreach ($supportedLangs as $lang) {
            $langUrl = $baseUrl . '/' . $lang . ($path === '/' ? '' : $path);
            $alternates .= '    <xhtml:link rel="alternate" hreflang="' . htmlspecialchars($lang) . '" href="' . htmlspecialchars($langUrl) . '" />' . PHP_EOL;
        }
    }
    return $alternates;
}

// 1. Add Startpage (if it exists and is public)
$startpageJson = rtrim($rootDir, '/') . '/page.json';
$isStartpagePublic = true;
if (file_exists($startpageJson)) {
    $data = readJson($startpageJson, null);
    if (!empty($data['isPrivate'])) {
        $isStartpagePublic = false;
    }
}

if ($isStartpagePublic) {
    $alternates = getSitemapAlternates($baseUrl, '', $i18nEnabled, $supportedLangs, $defaultLang);

    echo '  <url>' . PHP_EOL;
    echo '    <loc>' . htmlspecialchars($baseUrl . '/') . '</loc>' . PHP_EOL;
    echo $alternates;
    echo '    <changefreq>daily</changefreq>' . PHP_EOL;
    echo '    <priority>1.0</priority>' . PHP_EOL;
    echo '  </url>' . PHP_EOL;

    if ($i18nEnabled && !empty($supportedLangs)) {
        foreach ($supportedLangs as $lang) {
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

// 2. Add pages from tree
function renderSitemapNodes($nodes, $baseUrl, $i18nEnabled, $supportedLangs, $defaultLang) {
    foreach ($nodes as $node) {
        $path = '/' . trim($node['path'], '/');
        
        // Skip hidden pages or private pages (already filtered by getCachedPagesTree)
        $alternates = getSitemapAlternates($baseUrl, $path, $i18nEnabled, $supportedLangs, $defaultLang);
        
        $depth = count(explode('/', trim($node['path'], '/')));
        $priority = max(0.5, 1.0 - ($depth * 0.1));

        echo '  <url>' . PHP_EOL;
        echo '    <loc>' . htmlspecialchars($baseUrl . $path) . '</loc>' . PHP_EOL;
        echo $alternates;
        echo '    <changefreq>weekly</changefreq>' . PHP_EOL;
        echo '    <priority>' . number_format($priority, 1) . '</priority>' . PHP_EOL;
        echo '  </url>' . PHP_EOL;

        if ($i18nEnabled && !empty($supportedLangs)) {
            foreach ($supportedLangs as $lang) {
                $langUrl = $baseUrl . '/' . $lang . ($path === '/' ? '' : $path);
                echo '  <url>' . PHP_EOL;
                echo '    <loc>' . htmlspecialchars($langUrl) . '</loc>' . PHP_EOL;
                echo $alternates;
                echo '    <changefreq>weekly</changefreq>' . PHP_EOL;
                echo '    <priority>' . number_format($priority, 1) . '</priority>' . PHP_EOL;
                echo '  </url>' . PHP_EOL;
            }
        }

        if (!empty($node['children'])) {
            renderSitemapNodes($node['children'], $baseUrl, $i18nEnabled, $supportedLangs, $defaultLang);
        }
    }
}

renderSitemapNodes($tree, $baseUrl, $i18nEnabled, $supportedLangs, $defaultLang);

echo '</urlset>' . PHP_EOL;
