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

    $query = trim((string) $qParam);
    $lang = trim((string) $langParam);

    // Format check for Link Autocomplete
    $format = $_POST['format'] ?? $_GET['format'] ?? '';
    $isAutocomplete = (strpos($format, 'link-autocomplete') !== false);

    // Extended mode is used by the search page
    $isExtended = !empty($_POST['extended'] ?? $_GET['extended'] ?? '');

    if ($query === '' && !$isExtended) {
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
    $pageSize = 20;

    // Search page filters
    $filterTags = [];
    $sort = 'relevance';
    $offset = 0;
    if ($isExtended) {
        require_once __DIR__ . '/../../core/pagesettings.php';
        $filterTags = normalizeTags(explode(',', (string) ($_POST['tags'] ?? $_GET['tags'] ?? '')));
        $offset = max(0, (int) ($_POST['offset'] ?? $_GET['offset'] ?? 0));

        $sortParam = (string) ($_POST['sort'] ?? $_GET['sort'] ?? '');
        if (in_array($sortParam, ['relevance', 'newest', 'oldest', 'title'], true)) {
            $sort = $sortParam;
        }
        if ($query === '' && $sort === 'relevance') {
            $sort = 'newest';
        }
    }

    $scored = [];
    foreach (getVisibleSearchEntries($lang, isLoggedIn()) as $entry) {
        $score = 0;
        if ($query !== '') {
            $score = scoreSearchEntry($entry, $query);
            if ($score === 0) continue;
        }

        if (!empty($filterTags) && !searchEntryHasTags($entry, $filterTags)) continue;

        $entry['score'] = $score;
        $entry['date'] = substr((string) ($entry['modified'] ?? ''), 0, 10);
        $scored[] = $entry;
    }

    switch ($sort) {
        case 'newest':
            usort($scored, fn($a, $b) => strcmp($b['date'], $a['date']));
            break;
        case 'oldest':
            usort($scored, fn($a, $b) => strcmp($a['date'], $b['date']));
            break;
        case 'title':
            usort($scored, fn($a, $b) => strnatcasecmp($a['title'], $b['title']));
            break;
        default:
            usort($scored, fn($a, $b) => $b['score'] - $a['score']);
    }

    $total = count($scored);
    $scored = $isExtended ? array_slice($scored, $offset, $pageSize) : array_slice($scored, 0, $maxResults);

    $results = [];
    foreach ($scored as $entry) {
        $result = [
            'path'    => $entry['path'],
            'title'   => $entry['title'],
            'excerpt' => buildSearchExcerpt($entry, $query, $isExtended ? 260 : 120),
        ];
        if ($isExtended) {
            $result['description'] = $entry['description'] ?? '';
            $result['tags']        = $entry['tags'] ?? [];
            $result['date']        = $entry['date'];
        }
        $results[] = $result;
    }

    // Support for Editor.js Link Autocomplete
    if ($isAutocomplete) {
        $items = [];
        foreach ($results as $r) {
            // Search index stores paths with language prefix (like /de/page)
            // For Autocomplete the prefix must be removed because the parser adds it at render time
            $hrefPath = $r['path'];
            if ($lang !== '' && str_starts_with($hrefPath, '/' . $lang . '/')) {
                $hrefPath = substr($hrefPath, strlen('/' . $lang));
            } elseif ($lang !== '' && $hrefPath === '/' . $lang) {
                $hrefPath = '/';
            }
            $items[] = [
                'href' => $hrefPath,
                'name' => $r['title'],
                'description' => strip_tags($r['excerpt'])
            ];
        }
        $response['success'] = true;
        $response['items'] = $items;
        return;
    }

    $response['success'] = true;
    $response['results'] = $results;
    if ($isExtended) {
        $response['total']    = $total;
        $response['has_more'] = ($offset + $pageSize) < $total;
    }
}
