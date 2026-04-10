<?php
/**
 * PureWiki - Block Parser
 *
 * Converts page content to HTML. Handles the conversion of Editor.js blocks
 * 
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

require_once realpath(__DIR__ . '/../extern/parsedown/Parsedown.php');
require_once realpath(__DIR__ . '/../extern/parsedownExtra/ParsedownExtra.php');
require_once realpath(__DIR__ . '/../core/fs.php');

function renderMarkdown(string $text): string {
    static $pd = null;
    if ($pd === null) {
        $pd = new ParsedownExtra();
        $pd->setSafeMode(true);
    }
    return $pd->text($text);
}

/** Adds BASE_PATH to internal href elements in an HTML string. */
function prefixInternalLinks(string $html): string {
    if (BASE_PATH === '') {
        return $html;
    }
    return preg_replace_callback(
        '/\bhref=(["\'])(\/)(?!\/)([^"\']*)\1/i',
        function ($m) {
            return 'href=' . $m[1] . BASE_PATH . '/' . $m[3] . $m[1];
        },
        $html
    );
}

function generateAnchor(string $text): string {
    $clean = strip_tags($text);
    return strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $clean), '-'));
}

require_once __DIR__ . '/../core/http.php';
require_once __DIR__ . '/../core/asset_manager.php';
require_once __DIR__ . '/../core/json.php';

function parseBlocksToHtml(array $blocks, string $contextPath = '/', ?array $mainBlocks = null): string {
    $parts = [];

    foreach ($blocks as $block) {
        $type = $block['type'] ?? '';
        $data = $block['data'] ?? [];
        $cssClass  = trim($block['tunes']['cssClassTune']['cssClass'] ?? '');
        $alignment = trim($block['tunes']['textAlignTune']['alignment'] ?? '');
        $allowedAlignments = ['left', 'center', 'right', 'justify'];
        if (!in_array($alignment, $allowedAlignments, true)) {
            $alignment = '';
        }
        $partsBefore = count($parts);

        switch ($type) {
            case 'paragraph':
                $text = prefixInternalLinks(htmlspecialchars_decode($data['text'] ?? '', ENT_QUOTES));
                $parts[] = '<p>' . $text . '</p>';
                break;

            case 'header':
                $level = isset($data['level']) ? (int)$data['level'] : 2;
                if ($level < 1 || $level > 6) $level = 2;
                $text = prefixInternalLinks(htmlspecialchars_decode($data['text'] ?? '', ENT_QUOTES));
                $anchor = generateAnchor($text);
                $parts[] = '<h' . $level . ' id="' . $anchor . '">' . $text . '</h' . $level . '>';
                break;

            case 'toc':
                $start = $data['startLevel'] ?? 1;
                $end = $data['endLevel'] ?? 6;
                $tocLines = [];

                $sourceBlocks = ($mainBlocks !== null) ? $mainBlocks : $blocks;

                foreach ($sourceBlocks as $b) {
                    if (($b['type'] ?? '') === 'header') {
                        $hData = $b['data'] ?? [];
                        $hLevel = (int)($hData['level'] ?? 2);
                        if ($hLevel >= $start && $hLevel <= $end) {
                            $hText = htmlspecialchars_decode($hData['text'] ?? '', ENT_QUOTES);
                            $anchor = generateAnchor($hText);
                            $tocLines[] = '    <li class="pw-toc-level-' . $hLevel . '"><a href="#' . $anchor . '">' . $hText . '</a></li>';
                        }
                    }
                }

                if (!empty($tocLines)) {
                    $parts[] = '<div class="pw-toc-container">' . PHP_EOL
                        . '  <div class="pw-toc-title">Table of Contents</div>' . PHP_EOL
                        . '  <ul>' . PHP_EOL
                        . implode(PHP_EOL, $tocLines) . PHP_EOL
                        . '  </ul>' . PHP_EOL
                        . '</div>';
                }
                break;

            case 'list':
                $items = $data['items'] ?? [];
                $style = $data['style'] ?? 'unordered';
                $isChecklist = ($style === 'checklist');
                $tag = ($style === 'ordered') ? 'ol' : 'ul';

                $renderListItems = function ($items, $tag) use (&$renderListItems, $isChecklist) {
                    $html = '<' . $tag . '>';
                    foreach ($items as $item) {
                        $itemText = is_array($item)
                            ? prefixInternalLinks(htmlspecialchars_decode($item['content'] ?? '', ENT_QUOTES))
                            : prefixInternalLinks(htmlspecialchars_decode($item ?? '', ENT_QUOTES));

                        if ($isChecklist && is_array($item)) {
                            $checked = !empty($item['meta']['checked']) ? ' checked disabled' : ' disabled';
                            $itemText = '<input type="checkbox"' . $checked . '> ' . $itemText;
                        }

                        $children = (is_array($item) && !empty($item['items'])) ? $item['items'] : [];
                        $html .= '<li>' . $itemText;
                        if (!empty($children)) {
                            $html .= $renderListItems($children, $tag);
                        }
                        $html .= '</li>';
                    }
                    $html .= '</' . $tag . '>';
                    return $html;
                };

                $parts[] = $renderListItems($items, $tag);
                break;

            case 'delimiter':
                $parts[] = '<hr>';
                break;

            case 'raw':
                $parts[] = $data['html'] ?? '';
                break;

            case 'code':
                AssetManager::requirePrism();
                AssetManager::requireIconify();
                $code = htmlspecialchars($data['code'] ?? '', ENT_QUOTES);
                $lang = htmlspecialchars($data['language'] ?? 'none', ENT_QUOTES);
                $class = ($lang !== 'none') ? ' class="language-' . $lang . '"' : '';
                $parts[] = '<div class="pw-code-wrapper">' . PHP_EOL
                    . '  <button class="pw-code-copy" title="Copy to clipboard" aria-label="Copy to clipboard"><iconify-icon icon="mdi:content-copy"></iconify-icon></button>' . PHP_EOL
                    . '  <pre' . $class . '><code' . $class . '>' . $code . '</code></pre>' . PHP_EOL
                    . '</div>';
                break;

            case 'table':
                $rows = $data['content'] ?? [];
                $withHeadings = $data['withHeadings'] ?? false;
                $tableParts = ['<table>'];
                foreach ($rows as $i => $row) {
                    $tag = ($withHeadings && $i === 0) ? 'th' : 'td';
                    $cells = [];
                    foreach ($row as $cell) {
                        $cellText = htmlspecialchars_decode($cell ?? '', ENT_QUOTES);
                        $cells[] = '    <' . $tag . '>' . $cellText . '</' . $tag . '>';
                    }
                    $tableParts[] = '  <tr>' . PHP_EOL . implode(PHP_EOL, $cells) . PHP_EOL . '  </tr>';
                }
                $tableParts[] = '</table>';
                $parts[] = implode(PHP_EOL, $tableParts);
                break;

            case 'image':
                $url = htmlspecialchars($data['url'] ?? '', ENT_QUOTES);
                $caption = htmlspecialchars_decode($data['caption'] ?? '', ENT_QUOTES);
                $showCaption = isset($data['showCaption']) ? (bool)$data['showCaption'] : true;

                if ($url) {
                    $webpUrl = getWebpUrl($data['url'] ?? '');
                    if (str_starts_with($webpUrl, '/') && !str_starts_with($webpUrl, '//')) {
                        $webpUrl = BASE_PATH . $webpUrl;
                    }
                    $figParts = ['<figure>', '  <img src="' . $webpUrl . '" alt="' . strip_tags($caption) . '">'];
                    if ($caption && $showCaption) {
                        $figParts[] = '  <figcaption>' . $caption . '</figcaption>';
                    }
                    $figParts[] = '</figure>';
                    $parts[] = implode(PHP_EOL, $figParts);
                }
                break;

            case 'callout':
                $style    = htmlspecialchars($data['style'] ?? $data['type'] ?? 'info', ENT_QUOTES);
                $header   = htmlspecialchars_decode($data['header'] ?? '', ENT_QUOTES);
                $text     = prefixInternalLinks(htmlspecialchars_decode($data['text'] ?? $data['message'] ?? '', ENT_QUOTES));
                $showIcon = $data['showIcon'] ?? true;
                $iconName = htmlspecialchars($data['icon'] ?? '', ENT_QUOTES);

                $defaultIcons = ['info' => 'mdi:information-outline', 'warning' => 'mdi:alert-outline', 'important' => 'mdi:alert-circle-outline'];
                $resolvedIcon = $iconName ?: ($defaultIcons[$style] ?? 'mdi:information-outline');

                $calloutParts = ['<div class="pw-callout pw-callout-' . $style . '">'];
                if ($showIcon) {
                    AssetManager::requireIconify();
                    $calloutParts[] = '  <iconify-icon icon="' . $resolvedIcon . '" class="pw-callout-icon"></iconify-icon>';
                }
                $calloutParts[] = '  <div class="pw-callout-content">';
                if ($header) {
                    $calloutParts[] = '    <strong class="pw-callout-header">' . $header . '</strong>';
                }
                $calloutParts[] = '    <div>' . $text . '</div>';
                $calloutParts[] = '  </div>';
                $calloutParts[] = '</div>';
                $parts[] = implode(PHP_EOL, $calloutParts);
                break;

            case 'markdown':
                $mdText = $data['markdown'] ?? '';
                $parts[] = prefixInternalLinks(renderMarkdown($mdText));
                break;

            case 'liveMarkdown':
                $url = $data['url'] ?? '';
                $headerFilter = trim($data['header'] ?? '');
                if ($url) {
                    $fetchedContent = fetchMarkdownUrl($url);
                    if ($fetchedContent !== false) {
                        if ($headerFilter !== '' && preg_match('/^(#{1,6})\s+(.+)$/', $headerFilter, $hMatch)) {
                            $filterLevel = strlen($hMatch[1]);
                            $filterText  = trim($hMatch[2]);
                            $lines       = explode("\n", $fetchedContent);
                            $capture     = false;
                            $section     = [];

                            foreach ($lines as $line) {
                                if (preg_match('/^(#{1,6})\s+(.+)$/', $line, $lm)) {
                                    $lineLevel = strlen($lm[1]);
                                    $lineText  = trim($lm[2]);
                                    if ($capture) {
                                        if ($lineLevel <= $filterLevel) {
                                            break;
                                        }
                                    } elseif ($lineLevel === $filterLevel && strcasecmp($lineText, $filterText) === 0) {
                                        $capture = true;
                                        $section[] = $line;
                                        continue;
                                    }
                                }
                                if ($capture) {
                                    $section[] = $line;
                                }
                            }

                            if (!empty($section)) {
                                $fetchedContent = implode("\n", $section);
                            }
                        }
                        $parts[] = renderMarkdown($fetchedContent);
                    } else {
                        $parts[] = '<div class="pw-livemd-error-msg" style="color: var(--pw-text-muted); font-style: italic; padding: 10px; border: 1px dashed var(--pw-border); border-radius: 4px;">Content not available at the moment</div>';
                    }
                }
                break;

            case 'pagelist':
                $startPath = $data['startPath'] ?? '/';
                $boldHeadings = isset($data['boldHeader']) ? (bool)$data['boldHeader'] : false;

                $pagesDir = getPageDir();
                $targetDir = $pagesDir . DIRECTORY_SEPARATOR . trim($startPath, '/\\');

                if (file_exists($targetDir) && is_dir($targetDir)) {
                    if (function_exists('getCachedPagesTree')) {
                        $tree = getCachedPagesTree($targetDir, trim($startPath, '/\\'));
                        if (function_exists('buildNavTree')) {
                            $parts[] = buildNavTree($tree, $contextPath, $boldHeadings);
                        }
                    }
                }
                break;

            case 'block':
                $bgColor = $data['bgColor'] ?? '';
                $textColor = $data['textColor'] ?? '';
                $padding = $data['padding'] ?? '0';
                $margin = $data['margin'] ?? '0';
                $fullsize = !empty($data['fullsize']);
                $link = $data['link'] ?? '';
                $alignH = $data['alignH'] ?? 'left';
                $alignV = $data['alignV'] ?? 'top';
                $minHeight = intval($data['minHeight'] ?? 0);
                $innerBlocks = $data['blocks'] ?? [];
                $innerHtml = parseBlocksToHtml($innerBlocks, $contextPath);

                $spacingToCss = function ($val) {
                    if (!$val || $val === '0') return '';
                    $parts = preg_split('/\s+/', trim($val));
                    $px = [];
                    foreach ($parts as &$v) {
                        $px[] = intval($v) . 'px';
                    }
                    unset($v);
                    return implode(' ', $px);
                };

                $styles = ['display:flex', 'flex-direction:column'];
                if ($bgColor) $styles[] = 'background-color:' . htmlspecialchars($bgColor, ENT_QUOTES);
                if ($textColor) $styles[] = 'color:' . htmlspecialchars($textColor, ENT_QUOTES);
                $padCss = $spacingToCss($padding);
                if ($padCss) $styles[] = 'padding:' . $padCss;
                $marCss = $spacingToCss($margin);
                if ($marCss) $styles[] = 'margin:' . $marCss;
                if ($minHeight > 0) $styles[] = 'min-height:' . $minHeight . 'px';
                if ($fullsize) { $styles[] = 'width:100%'; $styles[] = 'height:100%'; }

                $hMap = ['left' => 'flex-start', 'center' => 'center', 'right' => 'flex-end'];
                $vMap = ['top' => 'flex-start', 'center' => 'center', 'bottom' => 'flex-end'];
                $styles[] = 'align-items:' . ($hMap[$alignH] ?? 'flex-start');
                $styles[] = 'justify-content:' . ($vMap[$alignV] ?? 'flex-start');

                $styleAttr = ' style="' . htmlspecialchars(implode(';', $styles), ENT_QUOTES) . '"';

                if ($link) {
                    $safeLink = htmlspecialchars($link, ENT_QUOTES);
                    $parts[] = '<a href="' . $safeLink . '" class="pw-block-link"><div class="pw-block"' . $styleAttr . '>' . $innerHtml . '</div></a>';
                } else {
                    $parts[] = '<div class="pw-block"' . $styleAttr . '>' . $innerHtml . '</div>';
                }
                break;

            case 'grid':
                $columns = intval($data['columns'] ?? 2);
                $minWidth = intval($data['minWidth'] ?? 200);
                $cells = $data['cells'] ?? [];
                $gridStyle = "grid-template-columns: repeat(auto-fill, minmax(min({$minWidth}px, 100%), 1fr))";
                $gridParts = ['<div class="pw-grid" style="' . htmlspecialchars($gridStyle, ENT_QUOTES) . '">'];
                foreach ($cells as $gridCell) {
                    $cellBlocks = $gridCell['blocks'] ?? [];
                    $cellHtml = parseBlocksToHtml($cellBlocks, $contextPath);
                    $gridParts[] = '<div class="pw-grid-cell">' . $cellHtml . '</div>';
                }
                $gridParts[] = '</div>';
                $parts[] = implode(PHP_EOL, $gridParts);
                break;

            case 'accordion':
                $items = $data['items'] ?? [];
                $accParts = ['<div class="pw-accordion">'];
                foreach ($items as $accItem) {
                    $title = htmlspecialchars($accItem['title'] ?? '', ENT_QUOTES);
                    $open = !empty($accItem['defaultOpen']) ? ' open' : '';
                    $innerBlocks = $accItem['blocks'] ?? [];
                    $innerHtml = parseBlocksToHtml($innerBlocks, $contextPath);

                    $accParts[] = '<details class="pw-accordion-item"' . $open . '>';
                    $accParts[] = '  <summary class="pw-accordion-header">' . $title . '</summary>';
                    $accParts[] = '  <div class="pw-accordion-body">' . $innerHtml . '</div>';
                    $accParts[] = '</details>';
                }
                $accParts[] = '</div>';
                $parts[] = implode(PHP_EOL, $accParts);
                break;

            case 'pageinclude':
                $pagePath = $data['pagePath'] ?? '';
                if ($pagePath) {
                    $parts[] = renderPageInclude($pagePath, $contextPath);
                }
                break;

            case 'snippet':
                $snippetName = $data['snippetName'] ?? '';
                if ($snippetName) {
                    $parts[] = renderSnippet($snippetName, $contextPath);
                }
                break;

            default:
                break;
        }

        if (count($parts) > $partsBefore) {
            $lastIndex = count($parts) - 1;

            if ($cssClass || $alignment) {
                $attrs = '';
                if ($cssClass) {
                    $attrs .= ' class="' . htmlspecialchars($cssClass, ENT_QUOTES) . '"';
                }
                if ($alignment) {
                    $attrs .= ' style="text-align:' . $alignment . '"';
                }
                $parts[$lastIndex] = '<div' . $attrs . '>' . $parts[$lastIndex] . '</div>';
            }
        }
    }

    return implode(PHP_EOL, $parts);
}

/** Renders the content of another page */
function renderPageInclude(string $path, string $contextPath): string {
    static $includeStack = [];

    $path = '/' . ltrim(str_replace(['\\', '..'], ['/', ''], $path), '/');

    // Circular reference protection
    if (in_array($path, $includeStack)) {
        return '<div class="pw-error">Circular include detected: ' . htmlspecialchars($path) . '</div>';
    }

    if (count($includeStack) > 10) {
        return '<div class="pw-error">Maximum include depth reached.</div>';
    }

    $includeStack[] = $path;

    // Resolve full file path
    $pagesDir = getPageDir();
    $targetFile = null;

    if (str_starts_with($path, '/_virtual/')) {
        $virtualName = substr($path, 10);
        $overridePath = $pagesDir . '/_virtual/' . $virtualName . '/page.json';
        $defaultPath  = getVirtualPagesDir() . '/_virtual/' . $virtualName . '/page.json';

        if (file_exists($overridePath)) {
            $targetFile = $overridePath;
        } elseif (file_exists($defaultPath)) {
            $targetFile = $defaultPath;
        }
    } else {
        $candidate = realpath($pagesDir . $path . '/page.json');
        // Must be inside pagesDir
        if ($candidate && str_starts_with($candidate, $pagesDir)) {
            $targetFile = $candidate;
        }
    }

    $html = '';
    if ($targetFile && file_exists($targetFile)) {
        $data = readJson($targetFile, null);
        if ($data && !empty($data['blocks'])) {
            $html = '<div class="pw-include-container" data-include-path="' . htmlspecialchars($path) . '">' . PHP_EOL;
            $html .= parseBlocksToHtml($data['blocks'], $contextPath);
            $html .= PHP_EOL . '</div>';
        }
    } else {
        $html = '<div class="pw-error"><iconify-icon icon="mdi:alert-circle-outline"></iconify-icon> Page not found: ' . htmlspecialchars($path) . '</div>';
    }

    array_pop($includeStack);
    return $html;
}

/** Renders the content of a snippet page */
function renderSnippet(string $snippetName, string $contextPath): string {
    static $snippetStack = [];

    $snippetName = preg_replace('/[^a-zA-Z0-9_-]/', '', $snippetName);
    if (!$snippetName) return '';

    // Circular reference protection
    if (in_array($snippetName, $snippetStack)) {
        return '<div class="pw-error">Circular snippet include detected: ' . htmlspecialchars($snippetName) . '</div>';
    }

    if (count($snippetStack) > 10) {
        return '<div class="pw-error">Maximum snippet include depth reached.</div>';
    }

    $snippetStack[] = $snippetName;

    // Resolve full file path
    $pagesDir = getPageDir();
    $targetFile = realpath($pagesDir . '/_snippets/' . $snippetName . '/page.json');

    $html = '';
    // Must be inside pagesDir
    if ($targetFile && str_starts_with($targetFile, $pagesDir)) {
        if (file_exists($targetFile)) {
            $data = readJson($targetFile, null);
            if ($data && !empty($data['blocks'])) {
                $html = '<div class="pw-snippet-container" data-snippet-name="' . htmlspecialchars($snippetName) . '">' . PHP_EOL;
                $html .= parseBlocksToHtml($data['blocks'], $contextPath);
                $html .= PHP_EOL . '</div>';
            }
        }
    } else {
        $html = '<div class="pw-error"><iconify-icon icon="mdi:alert-circle-outline"></iconify-icon> Snippet not found: ' . htmlspecialchars($snippetName) . '</div>';
    }

    array_pop($snippetStack);
    return $html;
}