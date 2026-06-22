<?php
/**
 * PureWiki - Diff Utility
 *
 * Provides functions to convert Editor.js block structures into plain text.
 * Computes line-by-line differences using the Longest Common Subsequence approach.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

/**
 * Calculates differences between two sets of text lines.
 *
 * @param array $oldLines
 * @param array $newLines
 * @return array List of diff elements with type ('unchanged', 'added', 'deleted') and text
 */
function calculateDiff(array $oldLines, array $newLines): array {
    $oldLineCount = count($oldLines);
    $newLineCount = count($newLines);
    $lcsMatrix = array_fill(0, $oldLineCount + 1, array_fill(0, $newLineCount + 1, 0));

    for ($oldIndex = 1; $oldIndex <= $oldLineCount; $oldIndex++) {
        for ($newIndex = 1; $newIndex <= $newLineCount; $newIndex++) {
            if ($oldLines[$oldIndex - 1] === $newLines[$newIndex - 1]) {
                $lcsMatrix[$oldIndex][$newIndex] = $lcsMatrix[$oldIndex - 1][$newIndex - 1] + 1;
            } else {
                $lcsMatrix[$oldIndex][$newIndex] = max($lcsMatrix[$oldIndex - 1][$newIndex], $lcsMatrix[$oldIndex][$newIndex - 1]);
            }
        }
    }

    $diff = [];
    $oldIndex = $oldLineCount;
    $newIndex = $newLineCount;

    while ($oldIndex > 0 || $newIndex > 0) {
        if ($oldIndex > 0 && $newIndex > 0 && $oldLines[$oldIndex - 1] === $newLines[$newIndex - 1]) {
            array_unshift($diff, ['type' => 'unchanged', 'text' => $oldLines[$oldIndex - 1]]);
            $oldIndex--;
            $newIndex--;
        } elseif ($newIndex > 0 && ($oldIndex === 0 || $lcsMatrix[$oldIndex][$newIndex - 1] >= $lcsMatrix[$oldIndex - 1][$newIndex])) {
            array_unshift($diff, ['type' => 'added', 'text' => $newLines[$newIndex - 1]]);
            $newIndex--;
        } else {
            array_unshift($diff, ['type' => 'deleted', 'text' => $oldLines[$oldIndex - 1]]);
            $oldIndex--;
        }
    }

    return $diff;
}

/**
 * Converts Editor.js block data into plain text lines for line comparing.
 *
 * @param array $blocks
 * @return array List of plain text lines
 */
function blocksToTextLines(array $blocks): array {
    $lines = [];

    foreach ($blocks as $block) {
        $type = $block['type'] ?? '';
        $data = $block['data'] ?? [];

        switch ($type) {
            case 'header':
                $level = $data['level'] ?? 2;
                $lines[] = str_repeat('#', $level) . ' ' . ($data['text'] ?? '');
                break;

            case 'paragraph':
            case 'quote':
                $lines[] = $data['text'] ?? '';
                break;

            case 'list':
                $items = $data['items'] ?? [];
                $style = ($data['style'] ?? 'unordered') === 'ordered' ? '1.' : '-';

                $parseListItems = function ($itemsList, $indentLevel = 0) use (&$parseListItems, &$lines, $style) {
                    $indent = str_repeat('  ', $indentLevel);
                    foreach ($itemsList as $item) {
                        if (is_array($item)) {
                            $content = $item['content'] ?? '';
                            $lines[] = $indent . $style . ' ' . $content;
                            if (!empty($item['items']) && is_array($item['items'])) {
                                $parseListItems($item['items'], $indentLevel + 1);
                            }
                        } else {
                            $lines[] = $indent . $style . ' ' . $item;
                        }
                    }
                };
                
                $parseListItems($items);
                break;

            case 'table':
                $content = $data['content'] ?? [];
                foreach ($content as $row) {
                    $lines[] = '| ' . implode(' | ', $row) . ' |';
                }
                break;

            case 'code':
                $code = $data['code'] ?? '';
                $codeLines = explode("\n", $code);
                foreach ($codeLines as $codeLine) {
                    $lines[] = $codeLine;
                }
                break;

            case 'callout':
                $lines[] = '> ' . ($data['title'] ?? '') . ': ' . ($data['text'] ?? '');
                break;

            case 'accordion':
                $items = $data['items'] ?? [];
                foreach ($items as $accItem) {
                    $title = $accItem['title'] ?? '';
                    $lines[] = '[Accordion: ' . $title . ']';
                    $innerBlocks = $accItem['blocks'] ?? [];
                    if (!empty($innerBlocks)) {
                        foreach (blocksToTextLines($innerBlocks) as $innerLine) {
                            $lines[] = '  ' . $innerLine;
                        }
                    }
                }
                break;

            case 'grid':
                $cells = $data['cells'] ?? [];
                foreach ($cells as $index => $cell) {
                    $lines[] = '[Grid Column ' . ($index + 1) . ']';
                    $cellBlocks = $cell['blocks'] ?? [];
                    if (!empty($cellBlocks)) {
                        foreach (blocksToTextLines($cellBlocks) as $cellLine) {
                            $lines[] = '  ' . $cellLine;
                        }
                    }
                }
                break;

            case 'block':
                $link = $data['link'] ?? '';
                if ($link) {
                    $lines[] = '[Block Link: ' . $link . ']';
                }
                $innerBlocks = $data['blocks'] ?? [];
                if (!empty($innerBlocks)) {
                    foreach (blocksToTextLines($innerBlocks) as $blockLine) {
                        $lines[] = $blockLine;
                    }
                }
                break;

            case 'image':
                $url = $data['url'] ?? '';
                $caption = $data['caption'] ?? '';
                $lines[] = '[Image: ' . basename($url) . ($caption ? ' - ' . $caption : '') . ']';
                break;

            case 'delimiter':
                $lines[] = '---';
                break;

            case 'pageinclude':
                $lines[] = '[Include Page: ' . ($data['pagePath'] ?? '') . ']';
                break;

            case 'snippet':
                $lines[] = '[Include Snippet: ' . ($data['snippetName'] ?? '') . ']';
                break;

            case 'math':
                $lines[] = '[Math: ' . ($data['math'] ?? '') . ']';
                break;

            case 'markdown':
                $mdText = $data['markdown'] ?? '';
                $mdLines = explode("\n", $mdText);
                foreach ($mdLines as $markdownLine) {
                    $lines[] = $markdownLine;
                }
                break;

            case 'liveMarkdown':
                $lines[] = '[Live Markdown URL: ' . ($data['url'] ?? '') . ']';
                break;

            case 'pagelist':
                $lines[] = '[Page List: ' . ($data['startPath'] ?? '/') . ']';
                break;

            case 'raw':
                $html = $data['html'] ?? '';
                $htmlLines = explode("\n", $html);
                foreach ($htmlLines as $htmlLine) {
                    $lines[] = $htmlLine;
                }
                break;

            case 'toc':
                $lines[] = '[Table of Contents]';
                break;

            default:
                if (isset($data['text'])) {
                    $lines[] = $data['text'];
                } elseif (isset($data['html'])) {
                    $lines[] = $data['html'];
                }
                break;
        }
    }

    return array_map('trim', $lines);
}
