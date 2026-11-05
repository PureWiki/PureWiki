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
require_once __DIR__ . '/../core/i18n_pages.php';

const PW_JSONLD_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

/**
 * Returns all SEO tags of a page in output order.
 * Tags: config, options, pageData, pageJsonPath, contextPath, title, seoTitle,
 * description, author, rawCreated, rawModified and wikiName.
 */
function buildSeoTags(array $seo): array {
    $tags = [];

    $robots = buildRobotsTag($seo);
    if ($robots !== null) $tags[] = $robots;

    $urls = buildPageUrls($seo);
    array_push($tags, ...buildPaginationLinks($urls));

    $canonical = buildCanonicalTag($seo, $urls['current']);
    if ($canonical !== null) $tags[] = $canonical;

    array_push($tags, ...buildHreflangLinks($seo));

    $image = resolveShareImageUrl($seo);
    array_push($tags, ...buildSocialTags($seo, $urls['current'], $image));

    $schema = buildStructuredData($seo, $urls['current'], $image);
    if ($schema !== null) $tags[] = $schema;

    return $tags;
}

/** Returns scheme and host of current request, like https://domain.de. */
function getSiteOrigin(): string {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
    return $protocol . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}

/** Checks if page is shown in its translation for current language. */
function pageHasTranslation(string $pageJsonPath): bool {
    return defined('CURRENT_LANG') && CURRENT_LANG !== '' && str_ends_with(basename($pageJsonPath), '.' . CURRENT_LANG . '.json');
}

/** Returns robots tag if indexing is off, else null. */
function buildRobotsTag(array $seo): ?string {
    if (!empty($seo['config']['seo_prevent_indexing']) || !empty($seo['options']['noindex'])) {
        return '<meta name="robots" content="noindex, nofollow">';
    }
    return null;
}

/** Returns absolute URLs of page with prev and next for result pages of a page list. */
function buildPageUrls(array $seo): array {
    // Pages without translation show default language content, so they point to its URL
    $langPrefix = pageHasTranslation($seo['pageJsonPath']) ? '/' . CURRENT_LANG : '';
    $currentUrl = rtrim(getSiteOrigin() . BASE_PATH, '/') . $langPrefix . rtrim($seo['contextPath'], '/');
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
function buildCanonicalTag(array $seo, string $url): ?string {
    if (empty($seo['config']['seo_auto_canonical'])) return null;
    return '<link rel="canonical" href="' . htmlspecialchars($url) . '">';
}

/** Returns hreflang links for default language and all published translations. */
function buildHreflangLinks(array $seo): array {
    if (empty($seo['config']['i18n_enabled'])) return [];

    $defaultLang = getDefaultPageLang();
    $pageDir = dirname($seo['pageJsonPath']);
    // Only languages with a published translation of this page
    $translatedLangs = array_filter(
        getSupportedPageLangs(),
        fn($lang) => $lang !== $defaultLang && file_exists($pageDir . '/' . getPageFilename($lang))
    );
    if (empty($translatedLangs)) return [];

    $baseUrl = rtrim(getSiteOrigin() . BASE_PATH, '/');
    $urlPath = rtrim($seo['contextPath'], '/');
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
function resolveShareImageUrl(array $seo): string {
    // Preview image of the page replaces the global one
    $pageImageUrl = (string) ($seo['pageData']['Settings']['preview_image'] ?? '');
    $globalImageUrl = $seo['config']['seo_og_image_url'] ?? '';
    if ($pageImageUrl === '' && empty($globalImageUrl)) return '';

    $imageUrl = $pageImageUrl !== '' ? $pageImageUrl : $globalImageUrl;
    // OpenGraph needs absolute URL with domain
    if (!preg_match('~^(?:f|ht)tps?://~i', $imageUrl)) {
        $imageUrl = getSiteOrigin() . ($pageImageUrl !== '' ? BASE_PATH : '') . '/' . ltrim($imageUrl, '/');
    }
    return $imageUrl;
}

/** Returns OpenGraph and Twitter (X) tags, image tags are added even with OpenGraph off. */
function buildSocialTags(array $seo, string $url, string $image): array {
    $config = $seo['config'];
    $description = $seo['description'];
    $shareTitle = $seo['seoTitle'] !== '' ? $seo['seoTitle'] : $seo['title'];
    $tags = [];

    if (!empty($config['seo_auto_opengraph'])) {
        $tags[] = '<meta property="og:title" content="' . htmlspecialchars($shareTitle) . '">';
        if (!empty($description)) {
            $tags[] = '<meta property="og:description" content="' . htmlspecialchars($description) . '">';
        }
        $tags[] = '<meta property="og:url" content="' . htmlspecialchars($url) . '">';
        $tags[] = '<meta property="og:site_name" content="' . htmlspecialchars($seo['wikiName']) . '">';
        $tags[] = '<meta property="og:type" content="' . ($seo['contextPath'] === '/' ? 'website' : 'article') . '">';
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
function buildStructuredData(array $seo, string $url, string $image): ?string {
    $config = $seo['config'];
    // No structured data for error and system pages
    if (empty($config['seo_schema_org']) || !empty($seo['options']['noindex']) || http_response_code() === 404) {
        return null;
    }

    $description = $seo['description'];

    // Startpage describes the wiki
    if ($seo['contextPath'] === '/') {
        $schemaData = [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => $seo['wikiName'],
            'url' => $url,
        ];
        if (!empty($description)) {
            $schemaData['description'] = $description;
        }
        if (pageHasTranslation($seo['pageJsonPath'])) {
            $schemaData['inLanguage'] = CURRENT_LANG;
        } elseif (!empty($config['i18n_enabled'])) {
            $schemaData['inLanguage'] = getDefaultPageLang();
        }
        return '<script type="application/ld+json">' . json_encode($schemaData, PW_JSONLD_FLAGS) . '</script>';
    }

    $schemaData = [
        '@context' => 'https://schema.org',
        '@type' => 'Article',
        'headline' => $seo['title'],
        'url' => $url,
    ];
    if (!empty($description)) {
        $schemaData['description'] = $description;
    }
    if (!empty($seo['author'])) {
        $schemaData['author'] = [
            '@type' => 'Person',
            'name' => $seo['author']
        ];
    }
    if (!empty($seo['rawCreated'])) {
        $schemaData['datePublished'] = date('c', strtotime($seo['rawCreated']));
    }
    if (!empty($seo['rawModified'])) {
        $schemaData['dateModified'] = date('c', strtotime($seo['rawModified']));
    }
    if ($image !== '') {
        $schemaData['image'] = $image;
    }
    return '<script type="application/ld+json">' . json_encode($schemaData, PW_JSONLD_FLAGS) . '</script>';
}

/** Returns page title from title format, SEO title of page replaces it. */
function resolvePageTitle(array $seo): string {
    if ($seo['seoTitle'] !== '') return $seo['seoTitle'];

    $titleFormat = $seo['config']['seo_title_format'] ?? '{{ page_title }} - {{ wiki_name }}';
    return str_replace(
        ['{{ page_title }}', '{{ wiki_name }}'],
        [$seo['title'], $seo['wikiName']],
        $titleFormat
    );
}
