<?php
/**
 * PureWiki - Search Action
 *
 * Implements the site-wide search logic. Scans the search index
 * and returns scored results with highlights and excerpts.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

if ($action === 'search') {
    $response['message'] = '';
    
    $qParam = $_POST['q'] ?? $_GET['q'] ?? '';
    $langParam = $_POST['lang'] ?? $_GET['lang'] ?? '';
    
    // Extraction from malformed URIs
    if ($qParam === '' && !empty($_SERVER['REQUEST_URI'])) {
        if (preg_match('/[?&]q=([^&]+)/', $_SERVER['REQUEST_URI'], $m)) {
            $qParam = urldecode($m[1]);
        }
    }
    
    $query = trim($qParam);
    $lang = trim($langParam);
    
    // Format check for Link Autocomplete
    $format = $_POST['format'] ?? $_GET['format'] ?? '';
    $isAutocomplete = (strpos($format, 'link-autocomplete') !== false);

    if ($query === '') {
        $response['success'] = true;
        if ($isAutocomplete) {
            $response['items'] = [];
            return;
        }
        $response['results'] = [];
        return;
    }

    $config = getGlobalConfig();
    $maxResults = (int)($config['search_max_results'] ?? 10);
    $index = getSearchIndex();
    
    // Determine the correct search set based on language
    $searchSet = [];
    if (isset($index['']) || isset($index[$lang])) {
        // grouped by language
        $searchSet = $index[$lang] ?? ($index[''] ?? []);
    } else {
        $searchSet = $index;
    }

    $scored = [];

    foreach ($searchSet as $entry) {
        $score = 0;

        if (stripos($entry['title'], $query) !== false) $score += 100;
        if (stripos($entry['description'], $query) !== false) $score += 50;
        if (stripos($entry['content'], $query) !== false) $score += 10;

        if ($score > 0) {
            $scored[] = [
                'path'        => $entry['path'],
                'title'       => $entry['title'],
                'description' => $entry['description'],
                'content'     => $entry['content'],
                'score'       => $score
            ];
        }
    }

    usort($scored, fn($a, $b) => $b['score'] - $a['score']);
    $scored = array_slice($scored, 0, $maxResults);

    foreach ($scored as &$r) {
        $excerpt = '';
        $fullText = $r['title'] . ' ' . $r['description'] . ' ' . $r['content'];
        $pos = stripos($fullText, $query);
        if ($pos !== false) {
            $start = max(0, $pos - 60);
            $length = strlen($query) + 120;
            $raw = substr($fullText, $start, $length);
            if ($start > 0) $raw = '…' . $raw;
            if ($start + $length < strlen($fullText)) $raw .= '…';
            $excerpt = preg_replace('/(' . preg_quote($query, '/') . ')/iu', '<mark>$1</mark>', htmlspecialchars($raw));
        }
        $r['excerpt'] = $excerpt;

        unset($r['description']);
        unset($r['content']);
        unset($r['score']);
    }
    unset($r);
    
    // Support for Editor.js Link Autocomplete
    if ($isAutocomplete) {
        $items = [];
        foreach ($scored as &$r) {
            $items[] = [
                'href' => $r['path'],
                'name' => $r['title'],
                'description' => strip_tags($r['excerpt'])
            ];
        }
        unset($r);
        $response['success'] = true;
        $response['items'] = $items;
        return;
    }

    $response['success'] = true;
    $response['results'] = $scored;
}
