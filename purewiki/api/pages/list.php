<?php
/**
 * PureWiki - List Pages Action
 *
 * Returns a flat list of all pages (path and title) for autocompletion.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

if ($action === 'list_pages') {
    $pagesDir = getPageDir();
    $virtualPagesDir = getVirtualPagesDir();

    $response['message'] = '';
    $results = [];

    $results[] = [
        'path' => '/',
        'title' => 'Startpage'
    ];

    $addNodesToResults = function(array $tree) use (&$results) {
        $flatNodes = [];
        flattenTree($tree, $flatNodes);
        foreach ($flatNodes as $node) {
            $results[] = [
                'path' => '/' . ltrim($node['path'], '/'),
                'title' => $node['name']
            ];
        }
    };


    $pagesTree = getCachedPagesTree($pagesDir);
    $addNodesToResults($pagesTree);

    // virtual pages
    $virtualUserDir = $pagesDir . '/_virtual';
    if (is_dir($virtualUserDir)) {
        $virtualUserTree = getCachedPagesTree($virtualUserDir, '_virtual');
        $addNodesToResults($virtualUserTree);
    }

    // system default pages
    $virtualSystemDir = $virtualPagesDir . '/_virtual';
    if (is_dir($virtualSystemDir)) {
        $virtualSystemTree = getCachedPagesTree($virtualSystemDir, '_virtual');
        foreach ($virtualSystemTree as $node) {
            $path = '/' . ltrim($node['path'], '/');

            $exists = false;
            foreach ($results as $res) {
                if ($res['path'] === $path) {
                    $exists = true;
                    break;
                }
            }
            if (!$exists) {
                $results[] = [
                    'path' => $path,
                    'title' => $node['name']
                ];
            }
        }
    }


    usort($results, fn($a, $b) => strcasecmp($a['path'], $b['path']));

    $response['success'] = true;
    $response['pages'] = $results;
}
