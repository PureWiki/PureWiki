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
    // Log page render time if debug mode is enabled
    $renderStart = isDebugMode() ? microtime(true) : 0.0;

    $config    = getGlobalConfig();
    $themeName = $config['current_theme'] ?? 'default';
    $pageData  = loadPageData($pageJsonPath, $options);
    $page      = buildPageContext($pageData, $fallbackTitle, $pageJsonPath, $contextPath, $config);

    $templates    = getTemplateCandidates($themeName, $page['layout'], !empty($options['template']) ? (string) $options['template'] : '');
    $templateFile = findTemplateFile($templates);
    if (!$templateFile) {
        pw_debug('No theme template found', ['theme' => $themeName, 'layout' => $page['layout']], 'WARN', 'renderer');
        return '<h1>No theme template found.</h1>';
    }

    // Without system template the macro is shown as content
    if (!empty($options['macro']) && $templateFile !== $templates[0]) {
        $page['contentHtml'] = resolveMacros('{{ macro:' . $options['macro'] . ' }}', ['contextPath' => $contextPath, 'pageData' => $pageData, 'config' => $config]);
    }

    $html = renderThemeTemplate($templateFile, [
        'title'        => $page['title'],
        'contentHtml'  => $page['contentHtml'],
        'pageData'     => $pageData,
        'config'       => $config,
        'contextPath'  => $contextPath,
        'wikiName'     => $page['wikiName'],
        'themeName'    => $themeName,
        'themeUrl'     => getThemeUrl($themeName),
        'description'  => $page['description'],
        'author'       => $page['author'],
        'dateCreated'  => $page['dateCreated'],
        'dateModified' => $page['dateModified'],
        'options'      => $options,
    ]);

    // Head hooks run before macros, so macros never see their output
    $customHead = buildCustomHeadAssets($config, $themeName);

    // Runs after parsing, page list sets result page and 404 status
    $faviconUrl = resolveFaviconUrl($config);
    $headTags = $faviconUrl !== '' ? ['<link rel="icon" href="' . htmlspecialchars($faviconUrl) . '">'] : [];
    array_push($headTags, ...buildSeoTags($page, $config, $options));

    // Macros and virtual pages first, icon check needs their output
    $html = resolveTemplateStructure($html, [
        'contextPath' => $contextPath,
        'wikiName' => $page['wikiName'],
        'pageData' => $pageData,
        'config' => $config
    ]);
    if (str_contains($html, '<iconify-icon') || str_contains($page['contentHtml'], '<iconify-icon')) {
        AssetManager::requireIconify();
    }

    // Footer hooks may add assets, so they run before head assets are built
    $assetsFooter = buildFooterAssets($config);
    $assetsHead   = buildHeadAssets($customHead, $headTags);

    $html = resolveTemplateVars($html, buildTemplateVars($page, $config, $themeName, $assetsHead, $assetsFooter));

    // In-Page Admin Menu
    injectAdminMenu($html, $config, $contextPath);

    if (class_exists('ExtensionLoader')) {
        $html = ExtensionLoader::applyFilter('renderer.html', $html, ['path' => $contextPath, 'page' => $pageData]);
    }

    if (isDebugMode()) {
        $elapsed = round((microtime(true) - $renderStart) * 1000, 2);
        pw_debug('Page rendered', [
            'path'     => $contextPath,
            'template' => basename($templateFile),
            'duration' => $elapsed . 'ms',
        ], 'INFO', 'renderer');
    }

    return $html;
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
        $userPath   = getPageDir() . '/_virtual/404/page.json';
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

/**
 * Returns possible template files in lookup order.
 * System template, then {layout}.php and page.php of the theme, then page.php of default theme.
 */
function getTemplateCandidates(string $themeName, string $layout, string $systemTemplate = ''): array {
    $themeBase = getThemesDir();
    $templates = [
        $themeBase . '/' . $themeName . '/' . $layout . '.php',
        $themeBase . '/' . $themeName . '/page.php',
        $themeBase . '/default/page.php',
    ];
    if ($systemTemplate !== '') {
        array_unshift($templates, $themeBase . '/' . $themeName . '/' . $systemTemplate . '.php');
    }
    return $templates;
}

/** Returns first existing template file, null if none exists. */
function findTemplateFile(array $templates): ?string {
    foreach ($templates as $template) {
        if (file_exists($template)) return $template;
    }
    return null;
}

/** Renders theme template, the template sees only the variables in $scope. */
function renderThemeTemplate(string $templateFile, array $scope): string {
    ob_start();
    (static function () {
        extract(func_get_arg(1));
        include func_get_arg(0);
    })($templateFile, $scope);
    return ob_get_clean();
}

/** Returns URL of theme folder with trailing slash. */
function getThemeUrl(string $themeName): string {
    return BASE_PATH . '/themes/' . $themeName . '/';
}

/** Returns favicon URL from settings, empty if none is set. */
function resolveFaviconUrl(array $config): string {
    if (empty($config['wiki_favicon'])) return '';
    $faviconUrl = $config['wiki_favicon'];
    return str_starts_with($faviconUrl, '/') ? BASE_PATH . $faviconUrl : $faviconUrl;
}

/** Wraps custom code in tag if it is not already included. */
function wrapCustomCode(string $code, string $tag): string {
    $code = trim($code);
    if ($code !== '' && stripos($code, '<' . $tag) === false) {
        $code = "<{$tag}>\n{$code}\n</{$tag}>";
    }
    return $code;
}

/**
 * Returns theme colors, custom code and output of head hooks.
 * Requires Iconify for admin menu before hooks, so asset order stays the same.
 */
function buildCustomHeadAssets(array $config, string $themeName): string {
    // Color overrides from theme settings
    $themeColorsCss = buildThemeColorsCss($config, $themeName);
    $customCss      = wrapCustomCode($config['custom_css'] ?? '', 'style');
    $customHtmlHead = trim($config['custom_html_head'] ?? '');
    $customJsHead   = wrapCustomCode($config['custom_js_head'] ?? '', 'script');

    if (isLoggedIn() && ($config['enable_admin_menu'] ?? false)) {
        AssetManager::requireIconify();
    }

    $extHead = '';
    if (class_exists('ExtensionLoader')) {
        ob_start();
        ExtensionLoader::doAction('frontend.head_css');
        ExtensionLoader::doAction('frontend.head_js');
        $extHead = ob_get_clean();
    }
    return $themeColorsCss . "\n" . $customCss . "\n" . $customHtmlHead . "\n" . $customJsHead . $extHead;
}

/** Returns all head assets, registered assets first, then custom code and head tags. */
function buildHeadAssets(string $customHead, array $headTags): string {
    // BASE_PATH and CURRENT_LANG for frontend JS
    $pwBasePathScript = "<script>window.PW_BASE_PATH = '" . addslashes(BASE_PATH) . "'; window.PW_CURRENT_LANG = '" . addslashes(defined('CURRENT_LANG') ? CURRENT_LANG : '') . "';</script>";

    $assetsHead = $pwBasePathScript . "\n" . AssetManager::getStyles() . AssetManager::getScripts('head') . "\n" . $customHead;
    if (!empty($headTags)) {
        $assetsHead .= "\n" . implode("\n", $headTags) . "\n";
    }
    return $assetsHead;
}

/** Returns footer assets, custom code and output of footer hooks. */
function buildFooterAssets(array $config): string {
    $extFooter = '';
    if (class_exists('ExtensionLoader')) {
        ob_start();
        ExtensionLoader::doAction('frontend.footer_js');
        $extFooter = ob_get_clean();
    }

    $customHtmlFooter = trim($config['custom_html_footer'] ?? '');
    $customJsFooter   = wrapCustomCode($config['custom_js_footer'] ?? '', 'script');
    return AssetManager::getScripts('footer') . AssetManager::getInlineScripts() . "\n" . $customHtmlFooter . "\n" . $customJsFooter . $extFooter;
}

/** Returns nav links of pages shown in navbar as list items. */
function buildNavLinksHtml(): string {
    $html = '';
    foreach (getNavLinks() as $link) {
        $linkText = !empty($link['link_text']) ? $link['link_text'] : $link['title'];
        $linkPath = $link['path'];
        // Internal links need BASE_PATH
        if (str_starts_with($linkPath, '/')) {
            $linkPath = BASE_PATH . $linkPath;
        }
        $html .= '<li><a href="' . htmlspecialchars($linkPath) . '">' . htmlspecialchars($linkText) . '</a></li>';
    }
    return $html;
}

/** Returns values for {{ placeholders }} in theme templates. */
function buildTemplateVars(array $page, array $config, string $themeName, string $assetsHead, string $assetsFooter): array {
    $pageData = $page['pageData'];
    return [
        'title'             => htmlspecialchars(resolvePageTitle($page, $config)),
        'page_title'        => htmlspecialchars($page['title']),
        'assets_head'       => $assetsHead,
        'assets_footer'     => $assetsFooter,
        'content'           => $page['contentHtml'],
        'date_created'      => htmlspecialchars($page['dateCreated']),
        'date_modified'     => htmlspecialchars($page['dateModified']),
        'date_created_iso'  => !empty($page['rawCreated']) ? date('Y-m-d', strtotime($page['rawCreated'])) : '',
        'date_modified_iso' => !empty($page['rawModified']) ? date('Y-m-d', strtotime($page['rawModified'])) : '',
        'description'       => htmlspecialchars($page['description']),
        'wiki_description'  => htmlspecialchars($config['wiki_description'] ?? ''),
        'wiki_name'         => htmlspecialchars($page['wikiName']),
        'wiki_logo'         => htmlspecialchars(str_starts_with($config['wiki_logo'] ?? '', '/') ? BASE_PATH . $config['wiki_logo'] : ($config['wiki_logo'] ?? '')),
        'wiki_favicon'      => htmlspecialchars(resolveFaviconUrl($config)),
        'author'            => htmlspecialchars($page['author']),
        'nav_links'         => buildNavLinksHtml(),
        'context_path'      => htmlspecialchars($page['contextPath']),
        'current_year'      => date('Y'),
        'current_theme'     => htmlspecialchars($themeName),
        'base_url'          => htmlspecialchars(BASE_PATH . ((defined('CURRENT_LANG') && CURRENT_LANG !== '') ? '/' . CURRENT_LANG . '/' : '/')),
        'theme_url'         => htmlspecialchars(getThemeUrl($themeName)),
        'pw_base_path'      => htmlspecialchars(BASE_PATH),
        'show_left_sidebar'  => !($pageData['Settings']['hide_left_sidebar']  ?? false),
        'show_right_sidebar' => !($pageData['Settings']['hide_right_sidebar'] ?? false),
        'tags'               => $page['tagsHtml'],
        'lang'               => htmlspecialchars(getFrontendLanguage()),
    ];
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

