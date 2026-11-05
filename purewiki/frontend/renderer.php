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
require_once __DIR__ . '/seo.php';
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
    $seoTags = [];
    $faviconUrl = '';

    // Log page render time if debug mode is enabled
    $renderStart = isDebugMode() ? microtime(true) : 0.0;

    // Load global config
    $config    = getGlobalConfig();
    $wikiName  = $config['wiki_name']     ?? 'PureWiki';
    $themeName = $config['current_theme'] ?? 'default';

    $pageData = loadPageData($pageJsonPath, $options);
    $page     = buildPageContext($pageData, $fallbackTitle, $pageJsonPath, $contextPath, $config);
    [
        'title' => $title, 'seoTitle' => $seoTitle, 'contentHtml' => $contentHtml,
        'rawCreated' => $rawCreated, 'rawModified' => $rawModified,
        'dateCreated' => $dateCreated, 'dateModified' => $dateModified,
        'description' => $description, 'author' => $author,
        'layout' => $pageLayout, 'tagsHtml' => $tagsHtml,
    ] = $page;

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

    // Favicon first, then SEO tags
    if (!empty($config['wiki_favicon'])) {
        $faviconUrl = $config['wiki_favicon'];
        if (str_starts_with($faviconUrl, '/')) {
            $faviconUrl = BASE_PATH . $faviconUrl;
        }
        $seoTags[] = '<link rel="icon" href="' . htmlspecialchars($faviconUrl) . '">';
    }

    // Runs after parsing, page list sets result page and 404 status
    array_push($seoTags, ...buildSeoTags($page, $config, $options));
    $renderedTitle = resolvePageTitle($page, $config);

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

/** Returns page data from options or page.json, else 404 page and sets 404 status. */
function loadPageData(string $pageJsonPath, array $options): ?array {
    if (isset($options['pageData'])) {
        $pageData = $options['pageData'];
    } elseif (file_exists($pageJsonPath)) {
        $pageData = readJson($pageJsonPath, null);
    } else {
        http_response_code(404);
        // User override of 404 page first, then system default
        $userPath   = __DIR__ . '/../../pages/_virtual/404/page.json';
        $systemPath = getVirtualPagesDir() . '/_virtual/404/page.json';

        $pageData = [];
        if (file_exists($userPath)) {
            $pageData = readJson($userPath, null);
        } elseif (file_exists($systemPath)) {
            $pageData = readJson($systemPath, null);
        }
    }
    return is_array($pageData) ? $pageData : null;
}

/**
 * Returns page values for templates and SEO tags.
 * Parses the blocks, so it sets result page of a page list and 404 status.
 */
function buildPageContext(?array $pageData, string $fallbackTitle, string $pageJsonPath, string $contextPath, array $config): array {
    $page = [
        'pageData'     => $pageData,
        'pageJsonPath' => $pageJsonPath,
        'contextPath'  => $contextPath,
        'wikiName'     => $config['wiki_name'] ?? 'PureWiki',
        'title'        => 'PureWiki',
        'seoTitle'     => '',
        'contentHtml'  => '',
        'rawCreated'   => '',
        'rawModified'  => '',
        'dateCreated'  => '',
        'dateModified' => '',
        'description'  => '',
        'author'       => '',
        'layout'       => 'page',
        'tagsHtml'     => '',
    ];

    // Fallback if 404 page is missing
    if (!$pageData) {
        $page['title'] = 'Page Not Found - PureWiki';
        $page['contentHtml'] = '<h2>404 - Not Found</h2><p>The page you are looking for does not exist.</p>';
        if ($contextPath !== '/') {
            $page['contentHtml'] .= '<a href="' . BASE_PATH . '/">Go to Startpage</a>';
        }
        return $page;
    }

    $page['title'] = $pageData['pagetitle'] ?? $fallbackTitle;
    $page['seoTitle'] = trim((string) ($pageData['Settings']['seo_title'] ?? ''));
    $page['contentHtml'] = parseBlocksToHtml($pageData['blocks'] ?? [], $contextPath);

    $rawCreated  = $pageData['DateCreated']  ?? '';
    $rawModified = $pageData['DateModified'] ?? '';
    $page['rawCreated']   = $rawCreated;
    $page['rawModified']  = $rawModified;
    $page['dateCreated']  = $rawCreated  ? date('d.m.Y', strtotime($rawCreated))  : '';
    $page['dateModified'] = $rawModified ? date('d.m.Y', strtotime($rawModified)) : '';

    $description = $pageData['description'] ?? $pageData['Description'] ?? '';
    if (empty($description) && !empty($config['wiki_description'])) {
        $description = $config['wiki_description'];
    }
    $page['description'] = $description;

    // Public display name only, the stored author is the login name
    require_once __DIR__ . '/../core/auth.php';
    $page['author'] = getUserDisplayName((string) ($pageData['Author'] ?? ''));

    // Layout without .php, fallback is page
    $rawLayout = $pageData['Settings']['Layout'] ?? '';
    if ($rawLayout !== '') {
        $page['layout'] = pathinfo($rawLayout, PATHINFO_FILENAME);
    }
    if (!empty($pageData['Tags']) && is_array($pageData['Tags'])) {
        $page['tagsHtml'] = renderTagsHtml($pageData['Tags'], $config);
    }
    return $page;
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

