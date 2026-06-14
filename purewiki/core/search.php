<?php
/**
 * PureWiki - Search Index
 *
 * Functions for building and querying the full-text search index
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

require_once __DIR__ . '/cache.php';

/**
 * Builds a full-text search index from all published pages.
 * Traverses /pages/ recursively, extracts plaintext from blocks,
 * and writes the index to cache/searchindex.json.
 *
 * @return array The generated search index.
 */
function buildSearchIndex(): array {
    $pagesDir = getPageDir();
    
    // Check if i18n is enabled
    $config = getGlobalConfig();
    $i18nEnabled = !empty($config['i18n_enabled']);
    $langs = ['']; // Always index default language
    if ($i18nEnabled) {
        require_once __DIR__ . '/i18n_pages.php';
        $supported = getSupportedPageLangs();
        foreach ($supported as $l) {
            $langs[] = $l;
        }
    }

    $index = [];
    foreach ($langs as $lang) {
        $index[$lang] = [];
    }

    _collectSearchEntries($pagesDir, $pagesDir, $index, $langs);

    $cacheDir = getCacheDir();
    if (!is_dir($cacheDir)) {
        require_once __DIR__ . '/fs.php';
        createDirectory($cacheDir);
    }
    writeJsonFile($cacheDir . '/searchindex.json', $index);
    return $index;
}

/**
 * Recursively collects search entries from all page directories.
 *
 * @param string $dir Current directory to scan.
 * @param string $pagesDir Root pages directory for relative path calculation.
 * @param array &$index Reference to the index array.
 * @param array $langs Array of language codes to index.
 */
function _collectSearchEntries(string $dir, string $pagesDir, array &$index, array $langs = ['']): void {
    foreach ($langs as $lang) {
        $filename = $lang === '' ? 'page.json' : 'page.' . $lang . '.json';
        $jsonPath = $dir . DIRECTORY_SEPARATOR . $filename;
        if (file_exists($jsonPath)) {
            $data = readJson($jsonPath, null);
            if (is_array($data)) {
                $relativePath = '/' . ltrim(str_replace(str_replace('\\', '/', $pagesDir), '', str_replace('\\', '/', $dir)), '/');
                $relativePath = '/' . ltrim($relativePath, '/');

                $textParts = [];
                if (!empty($data['blocks'])) {
                    _extractBlockText($data['blocks'], $textParts);
                }

                // Append language prefix to the path for search results
                $urlPath = $relativePath;
                if ($lang !== '') {
                    $urlPath = '/' . $lang . ($relativePath === '/' ? '' : $relativePath);
                }

                $index[$lang][] = [
                    'path'        => $urlPath,
                    'title'       => $data['pagetitle'] ?? basename($dir),
                    'description' => $data['description'] ?? $data['Description'] ?? '',
                    'content'     => implode(' ', $textParts)
                ];
            }
        }
    }

    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        if (str_starts_with($item, '_')) continue;

        $fullPath = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($fullPath)) {
            _collectSearchEntries($fullPath, $pagesDir, $index, $langs);
        }
    }
}

/**
 * Extracts plaintext from an array of Editor.js blocks.
 * Handles nested blocks in accordion, grid, and block containers.
 *
 * @param array $blocks The blocks array.
 * @param array &$parts Reference to the text parts array.
 */
function _extractBlockText(array $blocks, array &$parts): void {
    foreach ($blocks as $block) {
        $type = $block['type'] ?? '';
        $data = $block['data'] ?? [];

        switch ($type) {
            case 'paragraph':
            case 'header':
                _extractTextFromTextProperty($data, $parts);
                break;
            case 'list':
                _extractTextFromList($data, $parts);
                break;
            case 'markdown':
                _extractTextFromMarkdown($data, $parts);
                break;
            case 'callout':
                _extractTextFromCallout($data, $parts);
                break;
            case 'table':
                _extractTextFromTable($data, $parts);
                break;
            case 'code':
                _extractTextFromCode($data, $parts);
                break;
            case 'raw':
                _extractTextFromRaw($data, $parts);
                break;
            case 'accordion':
                _extractTextFromAccordion($data, $parts);
                break;
            case 'grid':
                _extractTextFromGrid($data, $parts);
                break;
            case 'block':
                _extractTextFromBlock($data, $parts);
                break;
            case 'image':
                _extractTextFromImage($data, $parts);
                break;
        }
    }
}

/**
 * Extracts plaintext from blocks that use the 'text' property (e.g. paragraph, header).
 *
 * @param array $data The block data.
 * @param array &$parts Reference to the text parts array.
 */
function _extractTextFromTextProperty(array $data, array &$parts): void {
    if (!empty($data['text'])) {
        $parts[] = strip_tags($data['text']);
    }
}

/**
 * Extracts plaintext from list blocks.
 *
 * @param array $data The block data.
 * @param array &$parts Reference to the text parts array.
 */
function _extractTextFromList(array $data, array &$parts): void {
    if (!empty($data['items'])) {
        _extractListText($data['items'], $parts);
    }
}

/**
 * Extracts plaintext from nested list items (Editor.js v2 format).
 *
 * @param array $items The list items array.
 * @param array &$parts Reference to the text parts array.
 */
function _extractListText(array $items, array &$parts): void {
    foreach ($items as $item) {
        if (!empty($item['content'])) {
            $parts[] = strip_tags($item['content']);
        }
        if (!empty($item['items'])) {
            _extractListText($item['items'], $parts);
        }
    }
}

/**
 * Extracts plaintext from markdown blocks.
 *
 * @param array $data The block data.
 * @param array &$parts Reference to the text parts array.
 */
function _extractTextFromMarkdown(array $data, array &$parts): void {
    if (!empty($data['markdown'])) {
        $parts[] = strip_tags($data['markdown']);
    }
}

/**
 * Extracts plaintext from callout blocks.
 *
 * @param array $data The block data.
 * @param array &$parts Reference to the text parts array.
 */
function _extractTextFromCallout(array $data, array &$parts): void {
    if (!empty($data['header'])) $parts[] = strip_tags($data['header']);
    if (!empty($data['text'])) $parts[] = strip_tags($data['text']);
}

/**
 * Extracts plaintext from table blocks.
 *
 * @param array $data The block data.
 * @param array &$parts Reference to the text parts array.
 */
function _extractTextFromTable(array $data, array &$parts): void {
    if (!empty($data['content'])) {
        foreach ($data['content'] as $row) {
            foreach ($row as $cell) {
                $clean = strip_tags($cell);
                if ($clean !== '') $parts[] = $clean;
            }
        }
    }
}

/**
 * Extracts plaintext from code blocks.
 *
 * @param array $data The block data.
 * @param array &$parts Reference to the text parts array.
 */
function _extractTextFromCode(array $data, array &$parts): void {
    if (!empty($data['code'])) {
        $parts[] = $data['code'];
    }
}

/**
 * Extracts plaintext from raw HTML blocks.
 *
 * @param array $data The block data.
 * @param array &$parts Reference to the text parts array.
 */
function _extractTextFromRaw(array $data, array &$parts): void {
    if (!empty($data['html'])) {
        $html = $data['html'];
        // Remove style blocks and contents
        $html = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $html);
        // Remove script blocks and contents
        $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);
        
        $clean = strip_tags($html);
        $clean = trim(preg_replace('/\s+/', ' ', $clean));
        if ($clean !== '') {
            $parts[] = $clean;
        }
    }
}

/**
 * Extracts plaintext from accordion blocks.
 *
 * @param array $data The block data.
 * @param array &$parts Reference to the text parts array.
 */
function _extractTextFromAccordion(array $data, array &$parts): void {
    if (!empty($data['items'])) {
        foreach ($data['items'] as $accItem) {
            if (!empty($accItem['title'])) $parts[] = strip_tags($accItem['title']);
            if (!empty($accItem['blocks'])) _extractBlockText($accItem['blocks'], $parts);
        }
    }
}

/**
 * Extracts plaintext from grid blocks.
 *
 * @param array $data The block data.
 * @param array &$parts Reference to the text parts array.
 */
function _extractTextFromGrid(array $data, array &$parts): void {
    if (!empty($data['cells'])) {
        foreach ($data['cells'] as $cell) {
            if (!empty($cell['blocks'])) _extractBlockText($cell['blocks'], $parts);
        }
    }
}

/**
 * Extracts plaintext from container blocks.
 *
 * @param array $data The block data.
 * @param array &$parts Reference to the text parts array.
 */
function _extractTextFromBlock(array $data, array &$parts): void {
    if (!empty($data['blocks'])) {
        _extractBlockText($data['blocks'], $parts);
    }
}

/**
 * Extracts plaintext from image blocks (captions).
 *
 * @param array $data The block data.
 * @param array &$parts Reference to the text parts array.
 */
function _extractTextFromImage(array $data, array &$parts): void {
    if (!empty($data['caption'])) {
        $parts[] = strip_tags($data['caption']);
    }
}

/**
 * Returns the search index from cache, or rebuilds it if missing.
 *
 * @return array The search index array.
 */
function getSearchIndex(): array {
    $cacheFile = getCacheDir() . '/searchindex.json';

    if (file_exists($cacheFile)) {
        $data = readJson($cacheFile, null);
        if (is_array($data)) return $data;
    }

    return buildSearchIndex();
}
