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
require_once __DIR__ . '/template.php';
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/tree.php';
require_once __DIR__ . '/../core/nav.php';
require_once __DIR__ . '/../core/media.php';
require_once __DIR__ . '/../core/theme.php';
require_once __DIR__ . '/../core/admin-menu.php';
require_once __DIR__ . '/../core/fs.php';
require_once __DIR__ . '/../core/i18n.php';
require_once __DIR__ . '/../version.php';

/**
 * Renders a full HTML page from a JSON file and a theme.
 * @param string $pageJsonPath Path to the page.json
 * @param string $fallbackTitle Title to use if not found in JSON
 * @param string $contextPath  Current request path
 * @param array $options Optional pageData, template, macro and noindex
 * @return string Final HTML output
 */
function renderPage(string $pageJsonPath, string $fallbackTitle, string $contextPath = '/', array $options = []): string {
    $title = 'PureWiki';
    $contentHtml = '';
    $dateCreated = '';
    $dateModified = '';
    $description = '';
    $seoTitle = '';
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

    if (isset($options['pageData'])) {
        $pageData = $options['pageData'];
    } elseif (file_exists($pageJsonPath)) {
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
            $seoTitle = trim((string) ($pageData['Settings']['seo_title'] ?? ''));
            $blocks = $pageData['blocks'] ?? [];
            $contentHtml = parseBlocksToHtml($blocks, $contextPath);

            $rawCreated   = $pageData['DateCreated']  ?? '';
            $rawModified  = $pageData['DateModified'] ?? '';
            $dateCreated  = $rawCreated  ? date('d.m.Y', strtotime($rawCreated))  : '';
            $dateModified = $rawModified ? date('d.m.Y', strtotime($rawModified)) : '';
            $description  = $pageData['description'] ?? $pageData['Description'] ?? '';
            if (empty($description) && !empty($config['wiki_description'])) {
                $description = $config['wiki_description'];
            }
            // Public display name only, the stored author is the login name
            require_once __DIR__ . '/../core/auth.php';
            $author       = getUserDisplayName((string) ($pageData['Author'] ?? ''));

            // Resolve layout from page settings (strip .php if present, fallback to 'page')
            $rawLayout  = $pageData['Settings']['Layout'] ?? '';
            if ($rawLayout !== '') {
                $pageLayout = pathinfo($rawLayout, PATHINFO_FILENAME);
            }
            if (!empty($pageData['Tags']) && is_array($pageData['Tags'])) {
                $tagsHtml = renderTagsHtml($pageData['Tags'], $config);
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

    // System templates first, fall back to the page layout
    if (!empty($options['template'])) {
        array_unshift($templates, $themeBase . '/' . $themeName . '/' . $options['template'] . '.php');
    }

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

    // Without system template the macro is shown as content
    if (!empty($options['macro']) && $templateFile !== $templates[0]) {
        $contentHtml = resolveMacros('{{ macro:' . $options['macro'] . ' }}', ['contextPath' => $contextPath, 'pageData' => $pageData, 'config' => $config]);
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

    // Color overrides from theme settings
    $themeColorsCss = buildThemeColorsCss($config, $themeName);

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

    $extHead = '';
    if (class_exists('ExtensionLoader')) {
        ob_start();
        ExtensionLoader::doAction('frontend.head_css');
        ExtensionLoader::doAction('frontend.head_js');
        $extHead = ob_get_clean();
    }

    // SEO Meta Tags Generation
    if (!empty($config['wiki_favicon'])) {
        $faviconUrl = $config['wiki_favicon'];
        if (str_starts_with($faviconUrl, '/')) {
            $faviconUrl = BASE_PATH . $faviconUrl;
        }
        $seoTags[] = '<link rel="icon" href="' . htmlspecialchars($faviconUrl) . '">';
    }

    if (!empty($config['seo_prevent_indexing']) || !empty($options['noindex'])) {
        $seoTags[] = '<meta name="robots" content="noindex, nofollow">';
    }

    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $hasTranslation = defined('CURRENT_LANG') && CURRENT_LANG !== '' && str_ends_with(basename($pageJsonPath), '.' . CURRENT_LANG . '.json');
    // Pages without translation show the default language content, so they point to its URL
    $langPrefix = $hasTranslation ? '/' . CURRENT_LANG : '';
    $urlPath = rtrim($contextPath, '/');
    $currentUrl = rtrim($protocol . $host . BASE_PATH, '/') . $langPrefix . $urlPath;

    // Result pages of a paginated page list
    ['page' => $resultPage, 'pages' => $resultPages] = pageRenderFlags();
    if ($resultPage > 0 && $resultPage <= $resultPages) {
        $baseUrl = $currentUrl;
        $pageUrl = fn(int $page) => $baseUrl . ($page > 1 ? '?page=' . $page : '');
        $currentUrl = $pageUrl($resultPage);
        if ($resultPage > 1) {
            $seoTags[] = '<link rel="prev" href="' . htmlspecialchars($pageUrl($resultPage - 1)) . '">';
        }
        if ($resultPage < $resultPages) {
            $seoTags[] = '<link rel="next" href="' . htmlspecialchars($pageUrl($resultPage + 1)) . '">';
        }
    }

    if (!empty($config['seo_auto_canonical'])) {
        $seoTags[] = '<link rel="canonical" href="' . htmlspecialchars($currentUrl) . '">';
    }

    if (!empty($config['i18n_enabled'])) {
        require_once __DIR__ . '/../core/i18n_pages.php';
        $supportedLangs = getSupportedPageLangs();
        $defaultLang = getDefaultPageLang();
        $pageDir = dirname($pageJsonPath);
        // Only languages with a published translation of this page
        $translatedLangs = array_filter(
            $supportedLangs,
            fn($lang) => $lang !== $defaultLang && file_exists($pageDir . '/' . getPageFilename($lang))
        );
        if (!empty($translatedLangs)) {
            $baseUrlLang = rtrim($protocol . $host . BASE_PATH, '/');
            $xDefaultUrl = $baseUrlLang . $urlPath;
            if ($xDefaultUrl === '') $xDefaultUrl = '/';
            
            $seoTags[] = '<link rel="alternate" hreflang="x-default" href="' . htmlspecialchars($xDefaultUrl) . '">';
            $seoTags[] = '<link rel="alternate" hreflang="' . htmlspecialchars($defaultLang) . '" href="' . htmlspecialchars($xDefaultUrl) . '">';

            foreach ($translatedLangs as $lang) {
                $langUrl = $baseUrlLang . '/' . $lang . $urlPath;
                $seoTags[] = '<link rel="alternate" hreflang="' . htmlspecialchars($lang) . '" href="' . htmlspecialchars($langUrl) . '">';
            }
        }
    }

    $isStartpage = $contextPath === '/';
    $shareTitle = $seoTitle !== '' ? $seoTitle : $title;

    if (!empty($config['seo_auto_opengraph'])) {
        $seoTags[] = '<meta property="og:title" content="' . htmlspecialchars($shareTitle) . '">';
        if (!empty($description)) {
            $seoTags[] = '<meta property="og:description" content="' . htmlspecialchars($description) . '">';
        }
        $seoTags[] = '<meta property="og:url" content="' . htmlspecialchars($currentUrl) . '">';
        $seoTags[] = '<meta property="og:site_name" content="' . htmlspecialchars($wikiName) . '">';
        $seoTags[] = '<meta property="og:type" content="' . ($isStartpage ? 'website' : 'article') . '">';
    }

    if (!empty($config['seo_twitter_cards'])) {
        $seoTags[] = '<meta name="twitter:card" content="summary_large_image">';
        $seoTags[] = '<meta name="twitter:title" content="' . htmlspecialchars($shareTitle) . '">';
        if (!empty($description)) {
            $seoTags[] = '<meta name="twitter:description" content="' . htmlspecialchars($description) . '">';
        }
    }

    // Preview image of the page replaces the global one
    $pageImageUrl = (string) ($pageData['Settings']['preview_image'] ?? '');
    if ($pageImageUrl !== '' || !empty($config['seo_og_image_url'])) {
        $imageUrl = $pageImageUrl !== '' ? $pageImageUrl : $config['seo_og_image_url'];
        // resolve relative image urls
        if (!preg_match('~^(?:f|ht)tps?://~i', $imageUrl)) {
            // If it starts with a slash, we make it absolute for OpenGraph (requires full URL)
            // OpenGraph usually works best with absolute URLs including domain.
            $imageUrl = $protocol . $host . ($pageImageUrl !== '' ? BASE_PATH : '') . '/' . ltrim($imageUrl, '/');
        }
        $seoTags[] = '<meta property="og:image" content="' . htmlspecialchars($imageUrl) . '">';
        if (!empty($config['seo_twitter_cards'])) {
            $seoTags[] = '<meta name="twitter:image" content="' . htmlspecialchars($imageUrl) . '">';
        }
    }

    // No structured data for error and system pages
    $hasSchema = !empty($config['seo_schema_org']) && empty($options['noindex']) && http_response_code() !== 404;

    // Startpage describes the wiki
    if ($hasSchema && $isStartpage) {
        $schemaData = [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => $wikiName,
            'url' => $currentUrl,
        ];
        if (!empty($description)) {
            $schemaData['description'] = $description;
        }

        if ($hasTranslation) {
            $schemaData['inLanguage'] = CURRENT_LANG;
        } elseif (!empty($config['i18n_enabled'])) {
            require_once __DIR__ . '/../core/i18n_pages.php';
            $schemaData['inLanguage'] = getDefaultPageLang();
        }
        $seoTags[] = '<script type="application/ld+json">' . json_encode($schemaData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . '</script>';
    } elseif ($hasSchema) {
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

    $titleFormat = $config['seo_title_format'] ?? '{{ page_title }} - {{ wiki_name }}';
    $renderedTitle = str_replace(
        ['{{ page_title }}', '{{ wiki_name }}'],
        [$title, $wikiName],
        $titleFormat
    );
    // SEO title from page settings replaces title format
    if ($seoTitle !== '') {
        $renderedTitle = $seoTitle;
    }

    // Macros and virtual pages first, icon check needs their output
    $finalOutput = resolveTemplateStructure($themeTemplate, [
        'contextPath' => $contextPath,
        'wikiName' => $wikiName,
        'pageData' => $pageData,
        'config' => $config
    ]);

    // Check if any icon is present in output or content
    if (str_contains($finalOutput, '<iconify-icon') || str_contains($contentHtml, '<iconify-icon')) {
        AssetManager::requireIconify();
    }

    $extFooter = '';
    if (class_exists('ExtensionLoader')) {
        ob_start();
        ExtensionLoader::doAction('frontend.footer_js');
        $extFooter = ob_get_clean();
    }

    $assetsHead = $pwBasePathScript . "\n" . AssetManager::getStyles() . AssetManager::getScripts('head') . "\n" . $themeColorsCss . "\n" . $customCss . "\n" . $customHtmlHead . "\n" . $customJsHead . $extHead;
    if (!empty($seoTags)) {
        $assetsHead .= "\n" . implode("\n", $seoTags) . "\n";
    }

    $assetsFooter = AssetManager::getScripts('footer') . AssetManager::getInlineScripts() . "\n" . $customHtmlFooter . "\n" . $customJsFooter . $extFooter;

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
        'lang'               => htmlspecialchars(getFrontendLanguage()),
    ];

    $finalOutput = resolveTemplateVars($finalOutput, $vars);

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

/** Returns the search page URL, empty if disabled. */
function getSearchPageUrl(array $config): string {
    if (empty($config['search_page_enabled'])) return '';
    $lang = defined('CURRENT_LANG') ? CURRENT_LANG : '';
    return BASE_PATH . ($lang !== '' ? '/' . $lang : '') . '/search';
}

/** Renders tags as list, linked to the search page if enabled. */
function renderTagsHtml(array $tags, array $config): string {
    if (empty($tags)) return '';

    $searchUrl = getSearchPageUrl($config);
    $html = '<div class="pw-tags"><span class="pw-tags-label">' . htmlspecialchars(__f('search.tags')) . ':</span><ul class="pw-tag-list">';
    foreach ($tags as $tag) {
        $label = htmlspecialchars((string) $tag);
        if ($searchUrl !== '') {
            $label = '<a href="' . htmlspecialchars($searchUrl . '?tags=' . rawurlencode((string) $tag)) . '">' . $label . '</a>';
        }
        $html .= '<li class="pw-tag">' . $label . '</li>';
    }
    return $html . '</ul></div>';
}

