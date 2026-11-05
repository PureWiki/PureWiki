<?php
/**
 * PureWiki - SEO Tags
 *
 * Builds robots, canonical, pagination, hreflang, OpenGraph,
 * X and Schema.org tags and the page title.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

require_once __DIR__ . '/parser.php';
require_once __DIR__ . '/../core/http.php';
require_once __DIR__ . '/../core/i18n_pages.php';

const PW_JSONLD_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

/** Returns all SEO tags of a page in output order, $page comes from buildPageContext(). */
function buildSeoTags(array $page, array $config, array $options): array {
    $tags = [];

    $robots = buildRobotsTag($config, $options);
    if ($robots !== null) $tags[] = $robots;

    $urls = buildPageUrls($page);
    array_push($tags, ...buildPaginationLinks($urls));

    $canonical = buildCanonicalTag($config, $urls['current']);
    if ($canonical !== null) $tags[] = $canonical;

    array_push($tags, ...buildHreflangLinks($page, $config));

    $image = resolveShareImageUrl($page, $config);
    array_push($tags, ...buildSocialTags($page, $config, $urls['current'], $image));

    $schema = buildStructuredData($page, $config, $options, $urls['current'], $image);
    if ($schema !== null) $tags[] = $schema;

    return $tags;
}

/** Checks if page is shown in its translation for current language. */
function pageHasTranslation(string $pageJsonPath): bool {
    return defined('CURRENT_LANG') && CURRENT_LANG !== '' && str_ends_with(basename($pageJsonPath), '.' . CURRENT_LANG . '.json');
}

/** Returns robots tag if indexing is off, else null. */
function buildRobotsTag(array $config, array $options): ?string {
    if (!empty($config['seo_prevent_indexing']) || !empty($options['noindex'])) {
        return '<meta name="robots" content="noindex, nofollow">';
    }
    return null;
}

/** Returns absolute URLs of page with prev and next for result pages of a page list. */
function buildPageUrls(array $page): array {
    // Pages without translation show default language content, so they point to its URL
    $langPrefix = pageHasTranslation($page['pageJsonPath']) ? '/' . CURRENT_LANG : '';
    $currentUrl = rtrim(getSiteOrigin() . BASE_PATH, '/') . $langPrefix . rtrim($page['contextPath'], '/');
    $urls = ['current' => $currentUrl, 'prev' => null, 'next' => null];

    // Result pages of paginated page list
    ['page' => $resultPage, 'pages' => $resultPages] = pageRenderFlags();
    if ($resultPage > 0 && $resultPage <= $resultPages) {
        $pageUrl = fn(int $page) => $currentUrl . ($page > 1 ? '?page=' . $page : '');
        $urls['current'] = $pageUrl($resultPage);
        if ($resultPage > 1) {
            $urls['prev'] = $pageUrl($resultPage - 1);
        }
        if ($resultPage < $resultPages) {
            $urls['next'] = $pageUrl($resultPage + 1);
        }
    }
    return $urls;
}

/** Returns prev and next links of result pages. */
function buildPaginationLinks(array $urls): array {
    $tags = [];
    if ($urls['prev'] !== null) {
        $tags[] = '<link rel="prev" href="' . htmlspecialchars($urls['prev']) . '">';
    }
    if ($urls['next'] !== null) {
        $tags[] = '<link rel="next" href="' . htmlspecialchars($urls['next']) . '">';
    }
    return $tags;
}

/** Returns canonical link if enabled, else null. */
function buildCanonicalTag(array $config, string $url): ?string {
    if (empty($config['seo_auto_canonical'])) return null;
    return '<link rel="canonical" href="' . htmlspecialchars($url) . '">';
}

/** Returns hreflang links for default language and all published translations. */
function buildHreflangLinks(array $page, array $config): array {
    if (empty($config['i18n_enabled'])) return [];

    $defaultLang = getDefaultPageLang();
    $pageDir = dirname($page['pageJsonPath']);
    // Only languages with a published translation of this page
    $translatedLangs = array_filter(
        getSupportedPageLangs(),
        fn($lang) => $lang !== $defaultLang && file_exists($pageDir . '/' . getPageFilename($lang))
    );
    if (empty($translatedLangs)) return [];

    $baseUrl = rtrim(getSiteOrigin() . BASE_PATH, '/');
    $urlPath = rtrim($page['contextPath'], '/');
    $xDefaultUrl = $baseUrl . $urlPath;
    if ($xDefaultUrl === '') $xDefaultUrl = '/';

    $tags = [
        '<link rel="alternate" hreflang="x-default" href="' . htmlspecialchars($xDefaultUrl) . '">',
        '<link rel="alternate" hreflang="' . htmlspecialchars($defaultLang) . '" href="' . htmlspecialchars($xDefaultUrl) . '">',
    ];
    foreach ($translatedLangs as $lang) {
        $tags[] = '<link rel="alternate" hreflang="' . htmlspecialchars($lang) . '" href="' . htmlspecialchars($baseUrl . '/' . $lang . $urlPath) . '">';
    }
    return $tags;
}

/** Returns absolute URL of share image, empty if none is set. */
function resolveShareImageUrl(array $page, array $config): string {
    // Preview image of the page replaces the global one
    $pageImageUrl = (string) ($page['pageData']['Settings']['preview_image'] ?? '');
    $globalImageUrl = $config['seo_og_image_url'] ?? '';
    if ($pageImageUrl === '' && empty($globalImageUrl)) return '';

    $imageUrl = $pageImageUrl !== '' ? $pageImageUrl : $globalImageUrl;
    // OpenGraph needs absolute URL with domain
    if (!preg_match('~^(?:f|ht)tps?://~i', $imageUrl)) {
        $imageUrl = getSiteOrigin() . BASE_PATH . '/' . ltrim($imageUrl, '/');
    }
    return $imageUrl;
}

/** Returns OpenGraph and Twitter (X) tags, image tags are added even with OpenGraph off. */
function buildSocialTags(array $page, array $config, string $url, string $image): array {
    $description = $page['description'];
    $shareTitle = $page['seoTitle'] !== '' ? $page['seoTitle'] : $page['title'];
    $tags = [];

    if (!empty($config['seo_auto_opengraph'])) {
        $tags[] = '<meta property="og:title" content="' . htmlspecialchars($shareTitle) . '">';
        if (!empty($description)) {
            $tags[] = '<meta property="og:description" content="' . htmlspecialchars($description) . '">';
        }
        $tags[] = '<meta property="og:url" content="' . htmlspecialchars($url) . '">';
        $tags[] = '<meta property="og:site_name" content="' . htmlspecialchars($page['wikiName']) . '">';
        $tags[] = '<meta property="og:type" content="' . ($page['contextPath'] === '/' ? 'website' : 'article') . '">';
    }

    if (!empty($config['seo_twitter_cards'])) {
        $tags[] = '<meta name="twitter:card" content="summary_large_image">';
        $tags[] = '<meta name="twitter:title" content="' . htmlspecialchars($shareTitle) . '">';
        if (!empty($description)) {
            $tags[] = '<meta name="twitter:description" content="' . htmlspecialchars($description) . '">';
        }
    }

    if ($image !== '') {
        $tags[] = '<meta property="og:image" content="' . htmlspecialchars($image) . '">';
        if (!empty($config['seo_twitter_cards'])) {
            $tags[] = '<meta name="twitter:image" content="' . htmlspecialchars($image) . '">';
        }
    }
    return $tags;
}

/** Returns JSON-LD script with WebSite for startpage and Article for other pages, null if disabled. */
function buildStructuredData(array $page, array $config, array $options, string $url, string $image): ?string {
    // No structured data for error and system pages
    if (empty($config['seo_schema_org']) || !empty($options['noindex']) || http_response_code() === 404) {
        return null;
    }

    $description = $page['description'];

    // Startpage describes the wiki
    if ($page['contextPath'] === '/') {
        $schemaData = [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => $page['wikiName'],
            'url' => $url,
        ];
        if (!empty($description)) {
            $schemaData['description'] = $description;
        }
        if (pageHasTranslation($page['pageJsonPath'])) {
            $schemaData['inLanguage'] = CURRENT_LANG;
        } elseif (!empty($config['i18n_enabled'])) {
            $schemaData['inLanguage'] = getDefaultPageLang();
        }
        return '<script type="application/ld+json">' . json_encode($schemaData, PW_JSONLD_FLAGS) . '</script>';
    }

    $schemaData = [
        '@context' => 'https://schema.org',
        '@type' => 'Article',
        'headline' => $page['title'],
        'url' => $url,
    ];
    if (!empty($description)) {
        $schemaData['description'] = $description;
    }
    if (!empty($page['author'])) {
        $schemaData['author'] = [
            '@type' => 'Person',
            'name' => $page['author']
        ];
    }
    if (!empty($page['rawCreated'])) {
        $schemaData['datePublished'] = date('c', strtotime($page['rawCreated']));
    }
    if (!empty($page['rawModified'])) {
        $schemaData['dateModified'] = date('c', strtotime($page['rawModified']));
    }
    if ($image !== '') {
        $schemaData['image'] = $image;
    }
    return '<script type="application/ld+json">' . json_encode($schemaData, PW_JSONLD_FLAGS) . '</script>';
}

/** Returns page title from title format, SEO title of page replaces it. */
function resolvePageTitle(array $page, array $config): string {
    if ($page['seoTitle'] !== '') return $page['seoTitle'];

    $titleFormat = $config['seo_title_format'] ?? '{{ page_title }} - {{ wiki_name }}';
    return str_replace(
        ['{{ page_title }}', '{{ wiki_name }}'],
        [$page['title'], $page['wikiName']],
        $titleFormat
    );
}
