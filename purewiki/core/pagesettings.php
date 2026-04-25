<?php
/**
 * PureWiki - Page Settings Management
 *
 * Functions for applying page metadata and settings from request data
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

/**
 * Applies page metadata and settings to a page data array.
 *
 * @param array $data The current page data array
 * @param array $postData Raw POST data from Browser
 * @return array The mutated page data array, safe for JSON serialization
 */
function applyPageSettings(array $data, array $postData): array {
    // Cast and sanitize incoming data immediately
    // Malformed structures here can break the parser when saving to JSON later
    if (isset($postData['description'])) {
        $data['Description'] = $postData['description'];
    }
    if (isset($postData['is_private'])) {
        $data['isPrivate'] = filter_var($postData['is_private'], FILTER_VALIDATE_BOOLEAN);
    }
    if (isset($postData['hide_in_treeview'])) {
        $data['Settings']['hide_in_treeview'] = filter_var($postData['hide_in_treeview'], FILTER_VALIDATE_BOOLEAN);
    }
    if (isset($postData['tags'])) {
        // Explode comma-separated tags, trim whitespace, and re-index the array
        // to ensure a clean, zero-indexed structure for the JSON file
        $tagsRaw = $postData['tags'];
        $tagsArray = array_map('trim', explode(',', $tagsRaw));
        $data['Tags'] = array_values(array_filter($tagsArray));
    }
    if (!isset($data['Settings']) || !is_array($data['Settings'])) {
        $data['Settings'] = [];
    }
    if (isset($postData['layout'])) {
        $data['Settings']['Layout'] = basename($postData['layout']);
    }
    if (isset($postData['hide_left_sidebar'])) {
        $data['Settings']['hide_left_sidebar'] = filter_var($postData['hide_left_sidebar'], FILTER_VALIDATE_BOOLEAN);
    }
    if (isset($postData['hide_right_sidebar'])) {
        $data['Settings']['hide_right_sidebar'] = filter_var($postData['hide_right_sidebar'], FILTER_VALIDATE_BOOLEAN);
    }
    if (isset($postData['include_in_navbar'])) {
        $data['Settings']['include_in_navbar'] = filter_var($postData['include_in_navbar'], FILTER_VALIDATE_BOOLEAN);
    }
    if (isset($postData['navbar_link_text'])) {
        $data['Settings']['navbar_link_text'] = trim($postData['navbar_link_text']);
    }
    if (isset($postData['enable_redirect'])) {
        $data['Settings']['enable_redirect'] = filter_var($postData['enable_redirect'], FILTER_VALIDATE_BOOLEAN);
    }
    if (isset($postData['redirect_url'])) {
        $data['Settings']['redirect_url'] = trim($postData['redirect_url']);
    }
    if (isset($postData['prevnext_enabled'])) {
        $data['Settings']['prevnext_enabled'] = filter_var($postData['prevnext_enabled'], FILTER_VALIDATE_BOOLEAN);
    }
    if (isset($postData['prevnext_scope'])) {
        $data['Settings']['prevnext_scope'] = basename($postData['prevnext_scope']);
    }

    if (class_exists('ExtensionLoader')) {
        $data = ExtensionLoader::applyFilter('page_settings.save', $data, ['post' => $postData]);
    }

    return $data;
}
