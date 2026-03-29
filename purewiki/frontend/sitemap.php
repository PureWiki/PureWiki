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

// Start XML output
header('Content-Type: application/xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;

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
    echo '  <url>' . PHP_EOL;
    echo '    <loc>' . htmlspecialchars($baseUrl . '/') . '</loc>' . PHP_EOL;
    echo '    <changefreq>daily</changefreq>' . PHP_EOL;
    echo '    <priority>1.0</priority>' . PHP_EOL;
    echo '  </url>' . PHP_EOL;
}

// 2. Add pages from tree
function renderSitemapNodes($nodes, $baseUrl) {
    foreach ($nodes as $node) {
        $path = '/' . trim($node['path'], '/');
        
        // Skip hidden pages or private pages (already filtered by getCachedPagesTree)
        
        echo '  <url>' . PHP_EOL;
        echo '    <loc>' . htmlspecialchars($baseUrl . $path) . '</loc>' . PHP_EOL;
        // Priority logic: shallower pages get higher priority
        $depth = count(explode('/', trim($node['path'], '/')));
        $priority = max(0.5, 1.0 - ($depth * 0.1));
        echo '    <changefreq>weekly</changefreq>' . PHP_EOL;
        echo '    <priority>' . number_format($priority, 1) . '</priority>' . PHP_EOL;
        echo '  </url>' . PHP_EOL;

        if (!empty($node['children'])) {
            renderSitemapNodes($node['children'], $baseUrl);
        }
    }
}

renderSitemapNodes($tree, $baseUrl);

echo '</urlset>' . PHP_EOL;
