<?php
/**
 * PureWiki - Template Engine
 *
 * Resolves macros, virtual pages, conditions and placeholders
 * in the HTML of theme templates.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

require_once __DIR__ . '/parser.php';
require_once __DIR__ . '/../core/fs.php';
require_once __DIR__ . '/../core/json.php';
require_once __DIR__ . '/../core/i18n_pages.php';

/** Resolves macros and virtual pages, $data holds contextPath, wikiName, pageData and config. */
function resolveTemplateStructure(string $html, array $data): string {
    $html = resolveMacros($html, $data);
    return resolveVirtualPages($html, $data['pageData'] ?: [], $data['contextPath']);
}

/** Resolves conditions and placeholders. */
function resolveTemplateVars(string $html, array $vars): string {
    $html = resolveConditions($html, $vars);
    return replacePlaceholders($html, $vars);
}

/** Replaces {{ name }} placeholders, unknown ones stay as they are. */
function replacePlaceholders(string $html, array $vars): string {
    return preg_replace_callback('/{{\s*([a-zA-Z0-9_]+)\s*}}/', function($matches) use ($vars) {
        $key = $matches[1];
        return $vars[$key] ?? $matches[0];
    }, $html);
}

/**
 * Resolves if / else / endif blocks.
 * Format: {{ if variable }} ... {{ else }} ... {{ endif }}
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
 * Resolves virtual pages in format {{ virtual:pagename }}.
 * Looks in /pages/_virtual/ first, then in /purewiki/data/pages/_virtual/.
 */
function resolveVirtualPages(string $html, array $parentPageData, string $contextPath): string {
    $pagesDir = getPageDir();

    return preg_replace_callback('/{{\s*virtual:\s*([a-zA-Z0-9_-]+)\s*}}/', function($matches) use ($pagesDir, $parentPageData, $contextPath) {
        $pageName = $matches[1];

        // Hidden sidebars from page settings
        if ($pageName === 'left_sidebar' && ($parentPageData['Settings']['hide_left_sidebar'] ?? false)) {
            return '';
        }
        if ($pageName === 'right_sidebar' && ($parentPageData['Settings']['hide_right_sidebar'] ?? false)) {
            return '';
        }

        $lang = defined('CURRENT_LANG') ? CURRENT_LANG : '';
        $filename = getPageFilename($lang);
        $defaultFilename = 'page.json';

        // Override in /pages/_virtual/
        $overridePath = $pagesDir . '/_virtual/' . $pageName . '/' . $filename;
        $overrideFallback = $pagesDir . '/_virtual/' . $pageName . '/' . $defaultFilename;

        // System default in /purewiki/data/pages/_virtual/
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
 * Resolves macros in format {{ macro:name }} or {{ macro:name | param }}.
 * Runs matching file in macros/, $data holds variables for macro scope.
 */
function resolveMacros(string $html, array $data = []): string {
    $macroDir = __DIR__ . '/macros/';

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
