<?php
/**
 * PureWiki - Main Routing
 *
 * Main routing file that handles all requests for the frontend and dashboard.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

// Main entry point guard
define('PUREWIKI', true);

// Basic routing
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$baseDir = dirname($scriptName);


// Clean path relative to installation directory
if ($baseDir === '/' || $baseDir === '\\') {
    $baseDir = '';
}
if ($baseDir !== '' && str_starts_with($requestUri, $baseDir)) {
    $path = substr($requestUri, strlen($baseDir));
} else {
    $path = $requestUri;
}
$path = '/' . ltrim($path, '/');

// Expose base path as a constant so all redirects and asset URLs stay within the installation sub-directory
define('BASE_PATH', $baseDir);

// Strip query parameters
$path = explode('?', $path)[0];

require_once __DIR__ . '/purewiki/core/exception.php';
require_once __DIR__ . '/purewiki/core/auth.php';
require_once __DIR__ . '/purewiki/core/config.php';

// Setup Redirect Logic
$isSetupAction = str_starts_with($path, '/setup') || (str_starts_with($path, '/purewiki/api.php') && ($_REQUEST['action'] ?? '') === 'setup_wiki');
if (!isSetupCompleted() && !$isSetupAction) {
    header('Location: ' . BASE_PATH . '/setup');
    exit;
}

// Block /setup after completion
if (isSetupCompleted() && str_starts_with($path, '/setup')) {
    header('Location: ' . BASE_PATH . '/dashboard');
    exit;
}

if ($path === '/sitemap.xml') {
    $config = getGlobalConfig();
    if (!empty($config['seo_enable_sitemap'])) {
        include 'purewiki/frontend/sitemap.php';
        exit;
    }
}

if (str_starts_with($path, '/setup')) {
    include 'purewiki/admin/setup.php';
    exit;
}

if (str_starts_with($path, '/dashboard/login')) {
    include 'purewiki/admin/login.php';
} elseif (str_starts_with($path, '/dashboard')) {
    // Guard: all dashboard routes require authentication
    if (!isLoggedIn()) {
        header('Location: ' . BASE_PATH . '/dashboard/login');
        exit;
    }

    if (str_starts_with($path, '/dashboard/media')) {
        if (!hasRole('editor')) { header('Location: ' . BASE_PATH . '/'); exit; }
        include 'purewiki/admin/media.php';
    } elseif (str_starts_with($path, '/dashboard/settings')) {
        if (!hasRole('admin')) { header('Location: ' . BASE_PATH . '/dashboard'); exit; }
        include 'purewiki/admin/settings.php';
    } elseif (str_starts_with($path, '/dashboard/page-settings')) {
        if (!hasRole('editor')) { header('Location: ' . BASE_PATH . '/'); exit; }
        include 'purewiki/admin/pageSettings.php';
    } elseif (str_starts_with($path, '/dashboard/edit')) {
        if (!hasRole('editor')) { header('Location: ' . BASE_PATH . '/'); exit; }
        include 'purewiki/admin/editor.php';
    } else {
        if (!hasRole('editor')) { header('Location: ' . BASE_PATH . '/'); exit; }
        include 'purewiki/admin/dashboard.php';
    }

} else {
    // Frontend Handling
    require_once __DIR__ . '/purewiki/frontend/renderer.php';
    
    $config = getGlobalConfig();
    $cacheEnabled = !empty($config['enable_cache']);
    $cacheLifetime = (int)($config['cache_lifetime'] ?? 3600);
    $cacheFile = '';

    if ($cacheEnabled && !isLoggedIn()) {
        $cacheFile = __DIR__ . '/cache/' . md5($path) . '.html';
        // Serve from cache if valid
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheLifetime) {
            readfile($cacheFile);
            exit;
        }
    }

    // Find the correct page.json
    require_once __DIR__ . '/purewiki/core/fs.php';
    $pagesDir = getPageDir();
    $targetPath = $path; // '/' or '/some/page'
    $safePath = sanitizePath($targetPath);
    
    // Check for preview mode
    $isPreview = isset($_GET['preview']) && $_GET['preview'] == '1' && isLoggedIn();
    if ($isPreview) {
        $cacheEnabled = false; // Disable cache for preview
    }

    $filename = 'page.json';
    if ($isPreview) {
        $draftFile = rtrim($pagesDir, '/') . '/' . (empty($safePath) ? '' : ltrim($safePath, '/') . '/') . 'page.draft.json';
        if (file_exists($draftFile)) {
            $filename = 'page.draft.json';
        }
    }

    // If $safePath is empty (root), the file is directly under /pages
    if (empty($safePath)) {
        $pageJsonPath = rtrim($pagesDir, '/') . '/' . $filename;
        $fallbackTitle = 'Startseite';
    } else {
        $pageJsonPath = rtrim($pagesDir, '/') . '/' . ltrim($safePath, '/') . '/' . $filename;
        $fallbackTitle = basename($safePath);
    }
    
    // Check for redirects before rendering (except in preview mode)
    if (!$isPreview && file_exists($pageJsonPath)) {
        $pageData = readJson($pageJsonPath, null);
        
        // Private Page Check
        if (!empty($pageData['isPrivate']) && !isLoggedIn()) {
            header('Location: ' . BASE_PATH . '/');
            exit;
        }

        // Custom Redirect Check
        if (!empty($pageData['Settings']['enable_redirect']) && !empty($pageData['Settings']['redirect_url'])) {
            header('Location: ' . $pageData['Settings']['redirect_url']);
            exit;
        }
    }
    $htmlOutput = renderPage($pageJsonPath, $fallbackTitle, $path);

    // Save to cache if enabled (only for public users)
    if ($cacheEnabled && $cacheFile && !isLoggedIn()) {
        file_put_contents($cacheFile, $htmlOutput);
    }
    // Output the page
    echo $htmlOutput;
}
