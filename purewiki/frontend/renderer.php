<?php
/**
 * PureWiki - Frontend Renderer
 *
 * Build the final HTML page. Manages theme templates,
 * asset injection, Macros and placeholder resolution for the frontend.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

require_once __DIR__ . '/parser.php';
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/tree.php';
require_once __DIR__ . '/../core/nav.php';
require_once __DIR__ . '/../core/media.php';
require_once __DIR__ . '/../core/admin-menu.php';
require_once __DIR__ . '/../core/fs.php';
require_once __DIR__ . '/../version.php';

/**
 * Renders a full HTML page from a JSON file and a theme.
 * @param string $pageJsonPath Path to the page.json
 * @param string $fallbackTitle Title to use if not found in JSON
 * @param string $contextPath  Current request path
 * @return string Final HTML output
 */
function renderPage(string $pageJsonPath, string $fallbackTitle, string $contextPath = '/'): string {
    $title = 'PureWiki';
    $contentHtml = '';
    $dateCreated = '';
    $dateModified = '';
    $description = '';
    $author = '';
    $pageLayout = 'page';
    $tagsHtml = '';
    $pageData = [];
    $seoTags = [];
    $faviconUrl = '';

    // Log page render time if debug mode is enabled
    $renderStart = isDebugMode() ? microtime(true) : 0.0;

    // Load global config
    $config    = getGlobalConfig();
    $wikiName  = $config['wiki_name']     ?? 'PureWiki';
    $themeName = $config['current_theme'] ?? 'default';

    if (file_exists($pageJsonPath)) {
        $pageData = readJson($pageJsonPath, null);
    } else {
        http_response_code(404);
        // Fallback to virtual 404 page (pages Folder Override first, then System Default)
        $UserPath   = __DIR__ . '/../../pages/_virtual/404/page.json';
        $SystemPath = getVirtualPagesDir() . '/_virtual/404/page.json';

        if (file_exists($UserPath)) {
            $pageData = readJson($UserPath, null);
        } elseif (file_exists($SystemPath)) {
            $pageData = readJson($SystemPath, null);
        }
    }

    if ($pageData) {
            $title = $pageData['pagetitle'] ?? $fallbackTitle;
            $blocks = $pageData['blocks'] ?? [];
            $contentHtml = parseBlocksToHtml($blocks, $contextPath);

            $rawCreated   = $pageData['DateCreated']  ?? '';
            $rawModified  = $pageData['DateModified'] ?? '';
            $dateCreated  = $rawCreated  ? date('d.m.Y', strtotime($rawCreated))  : '';
            $dateModified = $rawModified ? date('d.m.Y', strtotime($rawModified)) : '';
            $description  = $pageData['Description'] ?? '';
            if (empty($description) && !empty($config['wiki_description'])) {
                $description = $config['wiki_description'];
            }
            $author       = $pageData['Author']      ?? '';

            // Resolve layout from page settings (strip .php if present, fallback to 'page')
            $rawLayout  = $pageData['Settings']['Layout'] ?? '';
            if ($rawLayout !== '') {
                $pageLayout = pathinfo($rawLayout, PATHINFO_FILENAME);
            }
            if (!empty($pageData['Tags']) && is_array($pageData['Tags'])) {
                $tagsHtml = '<ul class="pw-tag-list">';
                foreach ($pageData['Tags'] as $tag) {
                    $tagsHtml .= '<li class="pw-tag">' . htmlspecialchars((string) $tag) . '</li>';
                }
                $tagsHtml .= '</ul>';
            }
    } else {
        // Fallback if _404 is missing
        $title = 'Page Not Found - PureWiki';
        $contentHtml = '<h2>404 - Not Found</h2><p>The page you are looking for does not exist.</p>';
        if ($contextPath !== '/') {
            $contentHtml .= '<a href="' . BASE_PATH . '/">Go to Startpage</a>';
        }
    }

    // Resolve the template file in the following order:
    // 1. themes/{current_theme}/{layout}.php
    // 2. themes/{current_theme}/page.php
    // 3. themes/default/page.php
    $themeBase   = realpath(__DIR__ . '/../../themes') ?: __DIR__ . '/../../themes';
    $templates = [
        $themeBase . '/' . $themeName . '/' . $pageLayout . '.php',
        $themeBase . '/' . $themeName . '/page.php',
        $themeBase . '/default/page.php',
    ];

    $templateFile = null;
    foreach ($templates as $currentTemplate) {
        if (file_exists($currentTemplate)) {
            $templateFile = $currentTemplate;
            break;
        }
    }

    if (!$templateFile) {
        pw_debug('No theme template found', ['theme' => $themeName, 'layout' => $pageLayout], 'WARN', 'renderer');
        return '<h1>No theme template found.</h1>';
    }

    $themeUrl = BASE_PATH . '/themes/' . $themeName . '/';

    ob_start();
    include $templateFile;
    $themeTemplate = ob_get_clean();

    // Build nav links
    $navLinksHtml = '';
    $navLinks = getNavLinks();
    foreach ($navLinks as $link) {
        $linkText = !empty($link['link_text']) ? $link['link_text'] : $link['title'];
        $linkPath = $link['path'];
        // Prepend BASE_PATH for internal links
        if (str_starts_with($linkPath, '/')) {
            $linkPath = BASE_PATH . $linkPath;
        }
        $navLinksHtml .= '<li><a href="' . htmlspecialchars($linkPath) . '">' . htmlspecialchars($linkText) . '</a></li>';
    }

    // inject custom code blocks
    $customCss = trim($config['custom_css'] ?? '');
    if ($customCss !== '' && stripos($customCss, '<style') === false) {
        $customCss = "<style>\n{$customCss}\n</style>";
    }

    $customJsHead = trim($config['custom_js_head'] ?? '');
    if ($customJsHead !== '' && stripos($customJsHead, '<script') === false) {
        $customJsHead = "<script>\n{$customJsHead}\n</script>";
    }

    $customJsFooter = trim($config['custom_js_footer'] ?? '');
    if ($customJsFooter !== '' && stripos($customJsFooter, '<script') === false) {
        $customJsFooter = "<script>\n{$customJsFooter}\n</script>";
    }

    $customHtmlHead = trim($config['custom_html_head'] ?? '');
    $customHtmlFooter = trim($config['custom_html_footer'] ?? '');

    if (isLoggedIn() && ($config['enable_admin_menu'] ?? false)) {
        AssetManager::requireIconify();
    }

    // Inject BASE_PATH and CURRENT_LANG into the frontend so JS can use it
    $pwBasePathScript = "<script>window.PW_BASE_PATH = '" . addslashes(BASE_PATH) . "'; window.PW_CURRENT_LANG = '" . addslashes(defined('CURRENT_LANG') ? CURRENT_LANG : '') . "';</script>";

    $assetsHead = $pwBasePathScript . "\n" . AssetManager::getStyles() . AssetManager::getScripts('head') . "\n" . $customCss . "\n" . $customHtmlHead . "\n" . $customJsHead;

    if (class_exists('ExtensionLoader')) {
        ob_start();
        ExtensionLoader::doAction('frontend.head_css');
        ExtensionLoader::doAction('frontend.head_js');
        $assetsHead .= ob_get_clean();
    }

    // SEO Meta Tags Generation
    if (!empty($config['wiki_favicon'])) {
        $faviconUrl = $config['wiki_favicon'];
        if (str_starts_with($faviconUrl, '/')) {
            $faviconUrl = BASE_PATH . $faviconUrl;
        }
        $seoTags[] = '<link rel="icon" href="' . htmlspecialchars($faviconUrl) . '">';
    }

    if (!empty($config['seo_prevent_indexing'])) {
        $seoTags[] = '<meta name="robots" content="noindex, nofollow">';
    }

    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $currentUrl = $protocol . $host . ($contextPath === '/' ? '' : $contextPath);

    if (!empty($config['seo_auto_canonical'])) {
        $seoTags[] = '<link rel="canonical" href="' . htmlspecialchars($currentUrl) . '">';
    }

    if (!empty($config['i18n_enabled'])) {
        require_once __DIR__ . '/../core/i18n_pages.php';
        $supportedLangs = getSupportedPageLangs();
        if (!empty($supportedLangs)) {
            $defaultLang = getDefaultPageLang();
            
            $baseUrlLang = rtrim($protocol . $host . BASE_PATH, '/');
            $cleanContextPath = ltrim($contextPath, '/');
            $pathAppend = ($cleanContextPath !== '') ? '/' . $cleanContextPath : '';

            $xDefaultUrl = $baseUrlLang . $pathAppend;
            if ($xDefaultUrl === '') $xDefaultUrl = '/';
            
            $seoTags[] = '<link rel="alternate" hreflang="x-default" href="' . htmlspecialchars($xDefaultUrl) . '">';
            $seoTags[] = '<link rel="alternate" hreflang="' . htmlspecialchars($defaultLang) . '" href="' . htmlspecialchars($xDefaultUrl) . '">';

            foreach ($supportedLangs as $lang) {
                $langUrl = $baseUrlLang . '/' . $lang . $pathAppend;
                $seoTags[] = '<link rel="alternate" hreflang="' . htmlspecialchars($lang) . '" href="' . htmlspecialchars($langUrl) . '">';
            }
        }
    }

    if (!empty($config['seo_auto_opengraph'])) {
        $seoTags[] = '<meta property="og:title" content="' . htmlspecialchars($title) . '">';
        if (!empty($description)) {
            $seoTags[] = '<meta property="og:description" content="' . htmlspecialchars($description) . '">';
        }
        $seoTags[] = '<meta property="og:url" content="' . htmlspecialchars($currentUrl) . '">';
        $seoTags[] = '<meta property="og:site_name" content="' . htmlspecialchars($wikiName) . '">';
        $seoTags[] = '<meta property="og:type" content="article">';
    }

    if (!empty($config['seo_twitter_cards'])) {
        $seoTags[] = '<meta name="twitter:card" content="summary_large_image">';
        $seoTags[] = '<meta name="twitter:title" content="' . htmlspecialchars($title) . '">';
        if (!empty($description)) {
            $seoTags[] = '<meta name="twitter:description" content="' . htmlspecialchars($description) . '">';
        }
    }

    if (!empty($config['seo_og_image_url'])) {
        $imageUrl = $config['seo_og_image_url'];
        // resolve relative image urls
        if (!preg_match('~^(?:f|ht)tps?://~i', $imageUrl)) {
            // If it starts with a slash, we make it absolute for OpenGraph (requires full URL)
            // OpenGraph usually works best with absolute URLs including domain.
            $imageUrl = $protocol . $host . '/' . ltrim($imageUrl, '/');
        }
        $seoTags[] = '<meta property="og:image" content="' . htmlspecialchars($imageUrl) . '">';
        if (!empty($config['seo_twitter_cards'])) {
            $seoTags[] = '<meta name="twitter:image" content="' . htmlspecialchars($imageUrl) . '">';
        }
    }

    if (!empty($config['seo_schema_org'])) {
        $schemaData = [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $title,
            'url' => $currentUrl,
        ];
        if (!empty($description)) {
            $schemaData['description'] = $description;
        }
        if (!empty($author)) {
            $schemaData['author'] = [
                '@type' => 'Person',
                'name' => $author
            ];
        }
        if (!empty($rawCreated)) {
            $schemaData['datePublished'] = date('c', strtotime($rawCreated));
        }
        if (!empty($rawModified)) {
            $schemaData['dateModified'] = date('c', strtotime($rawModified));
        }
        if (isset($imageUrl) && !empty($imageUrl)) {
            $schemaData['image'] = $imageUrl;
        }
        $seoTags[] = '<script type="application/ld+json">' . json_encode($schemaData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . '</script>';
    }

    if (!empty($seoTags)) {
        $assetsHead .= implode("\n", $seoTags) . "\n";
    }

    $titleFormat = $config['seo_title_format'] ?? '{{ page_title }} - {{ wiki_name }}';
    $renderedTitle = str_replace(
        ['{{ page_title }}', '{{ wiki_name }}'],
        [$title, $wikiName],
        $titleFormat
    );

    $assetsFooter = AssetManager::getScripts('footer') . "\n" . $customHtmlFooter . "\n" . $customJsFooter;

    if (class_exists('ExtensionLoader')) {
        ob_start();
        ExtensionLoader::doAction('frontend.footer_js');
        $assetsFooter .= ob_get_clean();
    }

    $finalOutput = $themeTemplate;

    // Resolve Macros first
    $finalOutput = resolveMacros($finalOutput, [
        'contextPath' => $contextPath,
        'wikiName' => $wikiName,
        'pageData' => $pageData,
        'config' => $config
    ]);

    // Resolve Virtual Pages
    $finalOutput = resolveVirtualPages($finalOutput, $pageData ?: [], $contextPath);

    // Replace all placeholders
    $vars = [
        'title'             => htmlspecialchars($renderedTitle),
        'page_title'        => htmlspecialchars($title),
        'assets_head'       => $assetsHead,
        'assets_footer'     => $assetsFooter,
        'content'           => $contentHtml,
        'date_created'      => htmlspecialchars($dateCreated),
        'date_modified'     => htmlspecialchars($dateModified),
        'date_created_iso'  => !empty($rawCreated) ? date('Y-m-d', strtotime($rawCreated)) : '',
        'date_modified_iso' => !empty($rawModified) ? date('Y-m-d', strtotime($rawModified)) : '',
        'description'       => htmlspecialchars($description),
        'wiki_description'  => htmlspecialchars($config['wiki_description'] ?? ''),
        'wiki_name'         => htmlspecialchars($wikiName),
        'wiki_logo'         => htmlspecialchars(str_starts_with($config['wiki_logo'] ?? '', '/') ? BASE_PATH . $config['wiki_logo'] : ($config['wiki_logo'] ?? '')),
        'wiki_favicon'      => htmlspecialchars($faviconUrl ?? ''),
        'author'            => htmlspecialchars($author),
        'nav_links'         => $navLinksHtml,
        'context_path'      => htmlspecialchars($contextPath),
        'current_year'      => date('Y'),
        'current_theme'     => htmlspecialchars($themeName),
        'base_url'          => htmlspecialchars(BASE_PATH . ((defined('CURRENT_LANG') && CURRENT_LANG !== '') ? '/' . CURRENT_LANG . '/' : '/')),
        'theme_url'         => htmlspecialchars($themeUrl),
        'pw_base_path'      => htmlspecialchars(BASE_PATH),
        'show_left_sidebar'  => !($pageData['Settings']['hide_left_sidebar']  ?? false),
        'show_right_sidebar' => !($pageData['Settings']['hide_right_sidebar'] ?? false),
        'tags'               => $tagsHtml,
    ];

    // Resolve conditions (if / else / endif)
    $finalOutput = resolveConditions($finalOutput, $vars);

    $finalOutput = preg_replace_callback('/{{\s*([a-zA-Z0-9_]+)\s*}}/', function($matches) use ($vars) {
        $key = $matches[1];
        return $vars[$key] ?? $matches[0]; // keep original if key is unknown
    }, $finalOutput);

    // In-Page Admin Menu
    injectAdminMenu($finalOutput, $config, $contextPath);

    if (class_exists('ExtensionLoader')) {
        $finalOutput = ExtensionLoader::applyFilter('renderer.html', $finalOutput, ['path' => $contextPath, 'page' => $pageData]);
    }

    if (isDebugMode()) {
        $elapsed = round((microtime(true) - $renderStart) * 1000, 2);
        pw_debug('Page rendered', [
            'path'     => $contextPath,
            'template' => basename($templateFile),
            'duration' => $elapsed . 'ms',
        ], 'INFO', 'renderer');
    }

    return $finalOutput;
}

/**
 * Resolves if / else / endif logic in the template.
 * Supported format: {{ if variable }} ... {{ else }} ... {{ endif }}
 *
 * @param string $html The HTML content to process
 * @param array $vars The variables available in the current context
 * @return string The processed HTML
 */
function resolveConditions(string $html, array $vars): string {
    $ifCount = preg_match_all('/{{\s*if\s+[a-zA-Z0-9_]+\s*}}/', $html);
    $endifCount = preg_match_all('/{{\s*endif\s*}}/', $html);

    if ($ifCount !== $endifCount) {
        return "<!-- Template Error: Mismatched {{ if }} and {{ endif }} count ($ifCount ifs, $endifCount endifs) -->\n" . $html;
    }

    $pattern = '/{{\s*if\s+([a-zA-Z0-9_]+)\s*}}((?:(?!{{\s*if\s+[a-zA-Z0-9_]+\s*}}).)*?){{\s*endif\s*}}/s';

    $loopCount = 0;
    while (preg_match('/{{\s*if\s+[a-zA-Z0-9_]+\s*}}/', $html) && $loopCount < 50) {
        $html = preg_replace_callback($pattern, function($matches) use ($vars) {
            $conditionKey = $matches[1];
            $innerBlock = $matches[2];

            $parts = preg_split('/{{\s*else\s*}}/', $innerBlock, 2);
            $trueBlock = $parts[0];
            $falseBlock = $parts[1] ?? '';

            $conditionValue = $vars[$conditionKey] ?? null;
            if (!empty($conditionValue)) {
                return $trueBlock;
            } else {
                return $falseBlock;
            }
        }, $html, -1, $count);

        if ($count === 0) {
            $html = "<!-- Template Error: Malformed {{ if }} blocks detected -->\n" . $html;
            break;
        }
        $loopCount++;
    }

    return $html;
}

/**
 * Resolves virtual pages in the format {{ virtual:pagename }}.
 * Dynamically looks up /purewiki/data/pages/_pagename/page.json and /pages/_pagename/page.json and parses its blocks.
 *
 * @param string $html The HTML content to process
 * @param array $parentPageData The data of the current page being rendered
 * @param string $contextPath The current request path
 * @return string The processed HTML with rendered virtual pages
 */
function resolveVirtualPages(string $html, array $parentPageData, string $contextPath): string {
    $pagesDir = getPageDir();

    // Regex matches {{ virtual:name }}
    return preg_replace_callback('/{{\s*virtual:\s*([a-zA-Z0-9_-]+)\s*}}/', function($matches) use ($pagesDir, $parentPageData, $contextPath) {
        $pageName = $matches[1];

        // apply sidebar visibility settings
        if ($pageName === 'left_sidebar' && ($parentPageData['Settings']['hide_left_sidebar'] ?? false)) {
            return '';
        }
        if ($pageName === 'right_sidebar' && ($parentPageData['Settings']['hide_right_sidebar'] ?? false)) {
            return '';
        }

        $lang = defined('CURRENT_LANG') ? CURRENT_LANG : '';
        $filename = getPageFilename($lang);
        $defaultFilename = 'page.json';

        // Check for override in /pages/_virtual/
        $overridePath = $pagesDir . '/_virtual/' . $pageName . '/' . $filename;
        $overrideFallback = $pagesDir . '/_virtual/' . $pageName . '/' . $defaultFilename;
        
        // Fallback to system default in /purewiki/data/pages/_virtual/
        $defaultPath = getVirtualPagesDir() . '/_virtual/' . $pageName . '/' . $filename;
        $defaultFallback = getVirtualPagesDir() . '/_virtual/' . $pageName . '/' . $defaultFilename;

        $virtualJson = null;
        if (file_exists($overridePath)) {
            $virtualJson = $overridePath;
        } elseif ($lang !== '' && file_exists($overrideFallback)) {
            $virtualJson = $overrideFallback;
        } elseif (file_exists($defaultPath)) {
            $virtualJson = $defaultPath;
        } elseif ($lang !== '' && file_exists($defaultFallback)) {
            $virtualJson = $defaultFallback;
        }

        if ($virtualJson) {
            $vData = readJson($virtualJson, null);
            if ($vData && !empty($vData['blocks'])) {
                return parseBlocksToHtml($vData['blocks'], $contextPath, $parentPageData['blocks'] ?? null);
            }
        }

        return '';
    }, $html);
}

/**
 * Resolves macros in the format {{ macro:macroname | param }}
 * by executing the corresponding PHP file in purewiki/frontend/macros/.
 *
 * @param string $html The HTML content to process
 * @param array $data Optional data to pass to the macro scope
 * @return string The processed HTML with resolved macros
 */
function resolveMacros(string $html, array $data = []): string {
    $macroDir = __DIR__ . '/macros/';

    // Regex matches {{ macro:name }} or {{ macro:name | param }}
    return preg_replace_callback('/{{\s*macro:\s*([a-zA-Z0-9_-]+)(?:\s*\|\s*([^}]+?))?\s*}}/', function($matches) use ($macroDir, $data) {
        $macroName = $matches[1];
        $macroParam = isset($matches[2]) ? trim($matches[2]) : null;
        $macroFile = $macroDir . $macroName . '.php';

        if (file_exists($macroFile)) {
            ob_start();
            $contextPath = $data['contextPath'] ?? '';
            $wikiName    = $data['wikiName'] ?? '';
            $pageData    = $data['pageData'] ?? [];
            $config      = $data['config'] ?? [];

            include $macroFile;
            return ob_get_clean();
        }

        return '<!-- Macro "' . htmlspecialchars($macroName) . '" not found -->';
    }, $html);
}

