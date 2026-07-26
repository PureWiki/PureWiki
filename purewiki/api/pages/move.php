<?php
/**
 * PureWiki - Page Movement
 *
 * Reorders pages within the hierarchy by adjusting their numerical
 * prefix for ordering among siblings.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

if ($action === 'drag_drop_page') {
    $sourcePath = $_POST['source_path'] ?? '';
    $targetPath = $_POST['target_path'] ?? '';
    $position = $_POST['position'] ?? ''; // 'before', 'after', 'inside'

    if (!$sourcePath || $sourcePath === '/' || !$targetPath || !in_array($position, ['before', 'after', 'inside'])) {
        $response['message'] = 'Invalid parameters for drag and drop.';
        return;
    }
    if ($sourcePath === $targetPath) {
        $response['message'] = 'Source and target cannot be the same.';
        return;
    }
    if (str_starts_with($sourcePath, '/_') || str_starts_with($targetPath, '/_')) {
        $response['message'] = 'System pages cannot be dragged or modified.';
        return;
    }
    if (str_starts_with($targetPath . '/', $sourcePath . '/')) {
        $response['message'] = 'Cannot move a folder into its own subfolder.';
        return;
    }

    $safeSource = sanitizePath($sourcePath);
    $safeTarget = sanitizePath($targetPath);

    $sourceDir = realpath($pagesDir . '/' . $safeSource);
    $targetDir = realpath($pagesDir . '/' . $safeTarget);

    if (!$sourceDir || !isPathInDir($sourceDir, $pagesDir)) {
        $response['message'] = 'Source folder invalid or access denied.';
        return;
    }

    // Find new parent directory for the source
    $newParentPath = '';
    if ($position === 'inside') {
        if ($targetPath === '/') {
            $newParentPath = '';
        } else {
            if (!$targetDir || !is_dir($targetDir)) {
                $response['message'] = 'Target folder invalid.';
                return;
            }
            $newParentPath = $safeTarget;
        }
    } else { // 'before' or 'after'
        if ($targetPath === '/') {
            $response['message'] = 'Cannot place siblings next to the root.';
            return;
        }
        $newParentPath = dirname($safeTarget);
        if ($newParentPath === '.') $newParentPath = '';
    }

    $folderName = basename($safeSource);
    if (str_starts_with($folderName, '_')) {
        $response['message'] = 'Page or folder names cannot start with an underscore.';
        return;
    }
    $newSourceRelPath = ($newParentPath ? $newParentPath . '/' : '') . $folderName;
    $newSourceDir = $pagesDir . '/' . $newSourceRelPath;

    // Physical move before reordering
    if ($sourceDir !== realpath($newSourceDir)) {
        if (file_exists($newSourceDir)) {
            $response['message'] = 'A folder with name "' . $folderName . '" already exists at the target location.';
            return;
        }
        if (!rename($sourceDir, $newSourceDir)) {
            $response['message'] = 'Failed to physically move the folder.';
            return;
        }
    }

    // Update sourceDir to new location
    $sourceDir = $newSourceDir;
    $sourcePath = '/' . ltrim($newSourceRelPath, '/');

    $treeSiblings = getCachedPagesTree($pagesDir, $newParentPath);
    $siblings = [];
    foreach ($treeSiblings as $node) {
        $nodeItemName = basename($node['path']);

        if ($nodeItemName !== $folderName) {
            $siblings[] = [
                'name' => $nodeItemName,
                'path' => $pagesDir . '/' . $node['path'],
                'order' => (int)$node['order']
            ];
        }
    }


    $sourceItemObj = [
        'name' => $folderName,
        'path' => $sourceDir,
        'order' => 0 // init order
    ];

    if ($position === 'inside') {
        $siblings[] = $sourceItemObj;
    } else {
        $targetName = basename($safeTarget);
        $targetIndex = array_search($targetName, array_column($siblings, 'name'));

        if ($targetIndex !== false) {
            if ($position === 'before') {
                array_splice($siblings, $targetIndex, 0, [$sourceItemObj]);
            } else if ($position === 'after') {
                array_splice($siblings, $targetIndex + 1, 0, [$sourceItemObj]);
            }
        } else {
            $siblings[] = $sourceItemObj;
        }
    }

    // Re-index orders strictly from 1
    foreach ($siblings as $i => $sib) {
        $newOrder = $i + 1;

        // Skip file write if the order didn't change
        if (isset($sib['order']) && $sib['order'] === $newOrder) {
            continue;
        }

        foreach (['page.json', 'page.draft.json'] as $jsonFile) {
            $jPath = $sib['path'] . '/' . $jsonFile;
            if (file_exists($jPath)) {
                $data = readJson($jPath, []);
                if (!isset($data['Order']) || (int)$data['Order'] !== $newOrder) {
                    $data['Order'] = $newOrder;
                    writeJsonFile($jPath, $data);
                }
            } else if ($sib['name'] === $folderName && $jsonFile === 'page.json') {
                 // create a basic page.json for the moved item if missing to persist order
                 $data = [
                    'pagetitle' => prepareTitle($folderName),
                    'Order' => $newOrder,
                    'DateCreated' => date('c'),
                    'DateModified' => date('c'),
                    'Author' => $_SESSION['pw_user'] ?? 'admin'
                 ];
                 writeJsonFile($jPath, $data);
            }
        }
    }

    invalidateTreeCache();
    rebuildNavLinksCache();
    invalidateSearchIndex();
    $response['success'] = true;
    $response['message'] = 'Page drag/drop layout saved.';
    logActivity('page_move', 'page', '/' . $safeSource, ['new_path' => $sourcePath]);
    clearCache();

} else if ($action === 'move_page') {
    $path = $_POST['path'] ?? '';
    $direction = $_POST['direction'] ?? '';

    if (!$path || $path === '/') {
        $response['message'] = 'Invalid path or cannot move root.';
        return;
    }
    if (str_starts_with($path, '/_')) {
        $response['message'] = 'System pages cannot be moved.';
        return;
    }
    if (!in_array($direction, ['up', 'down'])) {
        $response['message'] = 'Invalid direction.';
        return;
    }

    $safePath = sanitizePath($path);
    $targetDir = realpath($pagesDir . '/' . $safePath);

    if ($targetDir && isPathInDir($targetDir, $pagesDir) && is_dir($targetDir)) {
        $parentPath = dirname($safePath);
        if ($parentPath === '.') $parentPath = '';
        $parentDir = $pagesDir . ($parentPath ? '/' . $parentPath : '');

        // Load siblings
        $treeSiblings = getCachedPagesTree($pagesDir, $parentPath);
        $siblings = [];
        foreach ($treeSiblings as $node) {
            $siblings[] = [
                'name' => basename($node['path']),
                'path' => $pagesDir . '/' . $node['path'],
                'order' => (int)$node['order']
            ];
        }

        // Normalize orders starting from 1
        foreach ($siblings as $i => &$sib) {
            $sib['order'] = $i + 1;
        }
        unset($sib);

        // Find target index
        $targetName = basename($safePath);
        $targetIndex = array_search($targetName, array_column($siblings, 'name'));

        if ($targetIndex === false) {
            $response['message'] = 'Target page not found among siblings.';
            return;
        }

        // Determine swap index
        $swapIndex = ($direction === 'up') ? $targetIndex - 1 : $targetIndex + 1;

        if ($swapIndex < 0 || $swapIndex >= count($siblings)) {
            $response['message'] = 'Page cannot be moved further ' . $direction . '.';
            return;
        }

        // Swap orders
        $tempOrder = $siblings[$targetIndex]['order'];
        $siblings[$targetIndex]['order'] = $siblings[$swapIndex]['order'];
        $siblings[$swapIndex]['order'] = $tempOrder;

        // Save updated orders
        foreach ([$siblings[$targetIndex], $siblings[$swapIndex]] as $sib) {
            foreach (['page.json', 'page.draft.json'] as $jsonFile) {
                $jsonPath = $sib['path'] . '/' . $jsonFile;
                if (file_exists($jsonPath)) {
                    $data = readJson($jsonPath, []);
                    $data['Order'] = (int)$sib['order'];
                    writeJsonFile($jsonPath, $data);
                }
            }
        }

        invalidateTreeCache();
        $response['success'] = true;
        $response['message'] = 'Page moved successfully.';
        logActivity('page_move', 'page', '/' . $safePath, ['direction' => $direction]);
        clearCache();
    } else {
        $response['message'] = 'Source folder does not exist or access denied.';
    }
}
