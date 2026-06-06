<?php
/**
 * PureWiki - Page Tree
 *
 * Functions for building, caching, and rendering the hierarchical page tree
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

require_once __DIR__ . '/cache.php';
require_once __DIR__ . '/json.php';

/** Builds a hierarchical tree of the pages directory. */
function getPagesTree($dir, $basePath = '', $lang = '') {
    if (!function_exists('getPageFilename')) {
        require_once __DIR__ . '/i18n_pages.php';
    }

    $tree = [];
    if (!is_dir($dir)) return $tree;

    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        if (str_starts_with($item, '_')) continue; // Skip virtual/purewiki pages

        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            $relativePath = ltrim($basePath . '/' . $item, '/');

            $title = prepareTitle($item); // Fallback to folder name
            $order = 999;   // Fallback order
            $isPrivate = false;
            $hideInTreeview = false;
            $includeInNavbar = false;
            $navbarLinkText = '';

            $jsonFile = getPageFilename($lang, false);
            $draftJsonFile = getPageFilename($lang, true);

            $jsonPath = $path . DIRECTORY_SEPARATOR . $jsonFile;
            $draftPath = $path . DIRECTORY_SEPARATOR . $draftJsonFile;
            
            // Fallback to default if translated file does not exist
            if ($lang !== '' && !file_exists($jsonPath) && !file_exists($draftPath)) {
                $jsonFile = 'page.json';
                $draftJsonFile = 'page.draft.json';
                $jsonPath = $path . DIRECTORY_SEPARATOR . $jsonFile;
                $draftPath = $path . DIRECTORY_SEPARATOR . $draftJsonFile;
            }

            // Prioritize page.json, then page.draft.json
            $data = null;
            if (file_exists($jsonPath)) {
                $data = readJson($jsonPath, null);
            } elseif (file_exists($draftPath)) {
                $data = readJson($draftPath, null);
            }

            if (is_array($data)) {
                if (!empty($data['pagetitle'])) {
                    $title = $data['pagetitle'];
                }
                if (isset($data['Order'])) {
                    $order = (int)$data['Order'];
                }
                if (!empty($data['isPrivate'])) {
                    $isPrivate = true;
                }
                if (!empty($data['Settings']['hide_in_treeview'])) {
                    $hideInTreeview = true;
                }
                if (!empty($data['Settings']['include_in_navbar'])) {
                    $includeInNavbar = true;
                }
                if (!empty($data['Settings']['navbar_link_text'])) {
                    $navbarLinkText = $data['Settings']['navbar_link_text'];
                }
            }

            $hasDraft = file_exists($draftPath);

            $node = [
                'name' => $title,
                'path' => $relativePath,
                'order' => $order,
                'has_draft' => $hasDraft,
                'is_private' => $isPrivate,
                'hide_in_treeview' => $hideInTreeview,
                'include_in_navbar' => $includeInNavbar,
                'navbar_link_text' => $navbarLinkText,
                'children' => getPagesTree($path, $relativePath, $lang)
            ];
            $tree[] = $node;
        }
    }

    // Prioritize manual ordering (like dashboard UI).
    // Fallback to alphabetical to prevent pages jumping around on edits.
    usort($tree, function($a, $b) {
        if ($a['order'] === $b['order']) {
            return strcasecmp($a['name'], $b['name']);
        }
        return $a['order'] - $b['order'];
    });

    return $tree;
}

/**
 * Returns the pages tree from a JSON cache file.
 * If the cache file doesn't exist, it rebuilds from the filesystem and writes the cache.
 * Used by the frontend (parser / pagelist) where draft info is not needed.
 *
 * @param string $dir The pages directory path to scan.
 * @param string $basePath The base path for relative URLs.
 * @param string $lang The language code to use for building tree.
 * @return array The cached folder structure array.
 */
function getCachedPagesTree($dir, $basePath = '', $lang = '') {
    $cacheFilename = $lang !== '' ? 'pagetree.' . $lang . '.json' : 'pagetree.json';
    $cacheFile = getCacheDir() . '/' . $cacheFilename;
    $tree = null;

    if (file_exists($cacheFile)) {
        $cached = readJson($cacheFile, null);
        if (is_array($cached)) {
            $tree = $cached;
        }
    }

    if ($tree === null) {
        // Cache miss — rebuild from filesystem (full tree)
        $rootDir = getPageDir();
        $tree = stripDraftInfo(getPagesTree($rootDir, '', $lang));

        // Write cache
        $cacheDir = getCacheDir();
        if (!is_dir($cacheDir)) {
            require_once __DIR__ . '/fs.php';
            createDirectory($cacheDir);
        }
        writeJsonFile($cacheFile, $tree);
    }

    if (!function_exists('isLoggedIn')) require_once __DIR__ . '/../core/auth.php';
    if (!isLoggedIn()) $tree = filterPrivatePages($tree);

    return $basePath !== '' ? extractSubtree($tree, $basePath) : $tree;
}

/** Recursively filters out nodes with 'is_private' set to true. */
function filterPrivatePages(array $tree): array {
    $filtered = [];
    foreach ($tree as $node) {
        if (!empty($node['is_private'])) {
            continue; // Skip private page
        }
        if (!empty($node['children'])) {
            $node['children'] = filterPrivatePages($node['children']);
        }
        $filtered[] = $node;
    }
    return $filtered;
}

/** Recursively filters out nodes with 'hide_in_treeview' set to true. */
function filterHiddenPages(array $tree): array {
    $filtered = [];
    foreach ($tree as $node) {
        if (!empty($node['hide_in_treeview'])) {
            continue; // Skip page if hidden
        }
        if (!empty($node['children'])) {
            $node['children'] = filterHiddenPages($node['children']);
        }
        $filtered[] = $node;
    }
    return $filtered;
}

/** Recursively strips 'has_draft' from a tree. */
function stripDraftInfo(array $tree): array {
    foreach ($tree as &$node) {
        unset($node['has_draft']);
        if (!empty($node['children'])) {
            $node['children'] = stripDraftInfo($node['children']);
        }
    }
    unset($node); // break reference
    return $tree;
}

/**
 * Extracts a subtree matching the given basePath from a full tree.
 * Used when a widget or component only needs navigation links for a specific nested folder.
 */
function extractSubtree(array $tree, string $basePath): array {
    $segments = explode('/', trim($basePath, '/'));
    $current  = $tree;

    foreach ($segments as $seg) {
        $found = false;
        foreach ($current as $node) {
            $nodeName = basename($node['path']);
            if ($nodeName === $seg) {
                $current = $node['children'] ?? [];
                $found = true;
                break;
            }
        }
        if (!$found) return [];
    }
    return $current;
}

/** 
 * Recursively generates HTML for the dashboard treeview.
 * @param array $tree The page tree array from getCachedPagesTree()
 */
function buildAdminTree($tree) {
    if (empty($tree)) return '';

    $html = '<div class="pw-tree-children-wrapper"><ul class="pw-tree-children">';
    foreach ($tree as $node) {
        $hasChildren = !empty($node['children']);
        $nodeClass = $hasChildren ? 'pw-tree-node pw-has-children' : 'pw-tree-node';

        $html .= '<li class="' . $nodeClass . '">';
        $html .= '<div class="pw-tree-item" draggable="true" data-path="' . htmlspecialchars($node['path']) . '">';
        $html .= '<span class="pw-tree-toggle"></span>';
        $html .= '<span class="pw-tree-label">' . htmlspecialchars($node['name']) . '</span>';
        if (!empty($node['is_private'])) {
            $html .= '<span class="pw-tree-private-dot" title="' . htmlspecialchars(__('dashboard.private_page')) . '"></span>';
        }
        if (!empty($node['has_draft'])) {
            $html .= '<span class="pw-tree-draft-dot" title="' . htmlspecialchars(__('dashboard.unpublished_draft')) . '"></span>';
        }
        $html .= '</div>';

        if ($hasChildren) {
            $html .= buildAdminTree($node['children']);
        }

        $html .= '</li>';
    }
    $html .= '</ul></div>';

    return $html;
}

/**
 * Recursively generates a nested <ul>/<li> structure from a page tree for the frontend.
 * @param array $tree The page tree array from getCachedPagesTree()
 * @param string $currentPath The current context path for active states
 * @param bool $boldHeadings add bold styling class to headings
 * @param string $langPrefix language prefix for links
 * @return string The processed HTML
 */
function buildNavTree(array $tree, string $currentPath, bool $boldHeadings = false, string $langPrefix = ''): string {
    if (empty($tree)) return '';
    $currentPath = '/' . trim($currentPath, '/');
    if ($currentPath === '/') $currentPath = '';
    if ($langPrefix === '') {
        $langPrefix = (defined('CURRENT_LANG') && CURRENT_LANG !== '') ? '/' . CURRENT_LANG : '';
    }

    $html = '';
    $hasItems = false;
    foreach ($tree as $node) {
        if (!empty($node['hide_in_treeview'])) {
            continue; // Skip pages that should be hidden in treeview
        }
        $hasItems = true;
        $nodePath = '/' . trim($node['path'], '/');

        // Exact match
        $isActive = ($nodePath === $currentPath);
        // Parent folder match (current path starts with node path + /)
        $isOpen = (strpos($currentPath, $nodePath . '/') === 0);

        $classes = [];
        if ($isActive) $classes[] = 'active';
        if ($isOpen) $classes[] = 'open';
        if (!empty($node['children'])) $classes[] = 'has-children';

        $classStr = !empty($classes) ? ' class="' . implode(' ', $classes) . '"' : '';

        $html .= '<li' . $classStr . '>';

        if (!empty($node['children'])) {
            $html .= '<span class="pw-toggle"></span>';
        }

        $html .= '<a href="' . htmlspecialchars(BASE_PATH . $langPrefix . $nodePath) . '">' . htmlspecialchars($node['name']) . '</a>';

        if (!empty($node['children'])) {
            $html .= buildNavTree($node['children'], $currentPath, false, $langPrefix);
        }

        $html .= '</li>';
    }

    if (!$hasItems) return '';
    return '<ul class="pw-pagelist' . ($boldHeadings ? ' pw-pagelist-bold' : '') . '">' . $html . '</ul>';
}

/**
 * Returns the PREVIOUS and NEXT pages for a given path.
 * 
 * @param string $currentPath The relative path of the current page.
 * @param bool $includeHigherLevels If true, traverses up hierarchy.
 * @param string $lang The language code for tree cache.
 * @return array ['prev' => [path, name], 'next' => [path, name]]
 */
function getPageNeighbors(string $currentPath, bool $includeHigherLevels = false, string $lang = ''): array {
    $currentPath = '/' . trim($currentPath, '/');
    if ($currentPath === '/') $currentPath = '';

    $rootDir = getPageDir();
    $tree = getCachedPagesTree($rootDir, '', $lang);
    $tree = filterHiddenPages($tree);

    if ($includeHigherLevels) {
        $flatList = flattenTreeForNavigation($tree);
        $index = -1;
        foreach ($flatList as $i => $item) {
            $itemPath = '/' . trim($item['path'], '/');
            if ($itemPath === '/') $itemPath = '';
            if ($itemPath === $currentPath) {
                $index = $i;
                break;
            }
        }

        if ($index === -1) return ['prev' => null, 'next' => null];

        return [
            'prev' => ($index > 0) ? $flatList[$index - 1] : null,
            'next' => ($index < count($flatList) - 1) ? $flatList[$index + 1] : null
        ];
    }

    // Siblings only
    $segments = explode('/', trim($currentPath, '/'));
    if ($currentPath === '') $segments = [];
    $parentPath = count($segments) > 1 ? implode('/', array_slice($segments, 0, -1)) : '';

    $siblings = $parentPath === '' ? $tree : extractSubtree($tree, $parentPath);

    $sIndex = -1;
    foreach ($siblings as $i => $s) {
        $sPath = '/' . trim($s['path'], '/');
        if ($sPath === '/' && $s['path'] !== '') $sPath = ''; // normalize
        if ($sPath === $currentPath) {
            $sIndex = $i;
            break;
        }
    }

    if ($sIndex === -1) return ['prev' => null, 'next' => null];

    $prev = null;
    $next = null;

    if ($sIndex > 0) {
        $prev = ['path' => $siblings[$sIndex - 1]['path'], 'name' => $siblings[$sIndex - 1]['name']];
    }

    if (!empty($siblings[$sIndex]['children'])) {
        $firstChild = reset($siblings[$sIndex]['children']);
        $next = ['path' => $firstChild['path'], 'name' => $firstChild['name']];
    } else if ($sIndex < count($siblings) - 1) {
        $next = ['path' => $siblings[$sIndex + 1]['path'], 'name' => $siblings[$sIndex + 1]['name']];
    }

    return [
        'prev' => $prev,
        'next' => $next
    ];
}

/**
 * Recursively flattens a tree into a linear list of nodes.
 * @param array $tree The tree to flatten.
 * @param array $flat The referenced array holding the flattened nodes.
 */
function flattenTree(array $tree, array &$flat = []): void {
    foreach ($tree as $node) {
        $flat[] = $node;
        if (!empty($node['children'])) {
            flattenTree($node['children'], $flat);
        }
    }
}

/** Helper to flatten tree for navigation. */
function flattenTreeForNavigation(array $tree): array {
    $flat = [];
    $flatNodes = [];
    flattenTree($tree, $flatNodes);
    foreach ($flatNodes as $node) {
        $flat[] = ['path' => $node['path'], 'name' => $node['name']];
    }
    return $flat;
}
