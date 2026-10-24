<?php
/**
 * PureWiki - Frontend Macro: Search Page
 *
 * Renders the search form with tag filter and sorting. Results are loaded by search-page.js
 * and use the result template from elements/search_result.php of the active theme.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

if (empty($config['search_page_enabled'])) {
    return;
}

require_once __DIR__ . '/../../core/i18n.php';
require_once __DIR__ . '/../../core/asset_manager.php';
require_once __DIR__ . '/../../core/search.php';

AssetManager::addScript('search-page', BASE_PATH . '/purewiki/assets/js/search-page.js?v=' . PUREWIKI_VERSION);

// Theme result template, fallback to a basic card
$themeName = basename($config['current_theme'] ?? 'default');
$resultFile = __DIR__ . '/../../../themes/' . $themeName . '/elements/search_result.php';
if (file_exists($resultFile)) {
    $resultTemplate = file_get_contents($resultFile);
} else {
    $resultTemplate = '<article class="pw-search-card">'
        . '<a class="pw-search-card-title" data-pw-field="title"></a>'
        . '<p class="pw-search-card-tags" data-pw-field="tags"></p>'
        . '<p class="pw-search-card-excerpt" data-pw-field="excerpt"></p>'
        . '<time class="pw-search-card-date" data-pw-field="date"></time>'
        . '</article>';
}

$searchLang = defined('CURRENT_LANG') ? CURRENT_LANG : '';
$searchUrl = getSearchPageUrl($config);
$searchTags = getSearchTagCounts(getVisibleSearchEntries($searchLang, isLoggedIn()));
$searchTexts = [
    'no_results'   => __('search.no_results'),
    'failed'       => __('search.failed'),
    'result_count' => __('search.result_count'),
];

$e = fn($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<div class="pw-search-page" id="pw-search-page"
     data-api-endpoint="<?php echo $e(BASE_PATH . '/purewiki/api.php'); ?>"
     data-lang="<?php echo $e($searchLang); ?>"
     data-search-url="<?php echo $e($searchUrl); ?>"
     data-texts="<?php echo $e(json_encode($searchTexts, JSON_UNESCAPED_UNICODE)); ?>">
    <form class="pw-search-form" id="pw-search-form" role="search">
        <input type="search" name="q" class="pw-search-form-input" placeholder="<?php echo $e(__('search.placeholder')); ?>" aria-label="<?php echo $e(__('search.title')); ?>" autocomplete="off">
        <details class="pw-search-tags" id="pw-search-tags">
            <summary><?php echo $e(__('search.tags')); ?> <span id="pw-search-tags-count"></span></summary>
            <div class="pw-search-tags-panel">
                <?php if (empty($searchTags)): ?>
                    <p class="pw-search-empty"><?php echo $e(__('search.no_tags')); ?></p>
                <?php else: ?>
                    <input type="search" class="pw-search-tags-filter" id="pw-search-tags-filter" placeholder="<?php echo $e(__('search.filter_tags')); ?>" aria-label="<?php echo $e(__('search.filter_tags')); ?>" autocomplete="off">
                    <div class="pw-search-tags-list">
                        <?php foreach ($searchTags as $tag): ?>
                            <label class="pw-search-tag-option"><input type="checkbox" name="tags" value="<?php echo $e($tag['name']); ?>"> <?php echo $e($tag['name']); ?> <small><?php echo (int) $tag['count']; ?></small></label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </details>
        <select name="sort" class="pw-search-sort" aria-label="<?php echo $e(__('search.sort')); ?>">
            <option value="relevance"><?php echo $e(__('search.sort_relevance')); ?></option>
            <option value="newest"><?php echo $e(__('search.sort_newest')); ?></option>
            <option value="oldest"><?php echo $e(__('search.sort_oldest')); ?></option>
            <option value="title"><?php echo $e(__('search.sort_title')); ?></option>
        </select>
    </form>
    <p class="pw-search-count" id="pw-search-count" aria-live="polite"></p>
    <div class="pw-search-result-list" id="pw-search-result-list"></div>
    <button type="button" class="pw-search-more" id="pw-search-more" aria-label="<?php echo $e(__('search.load_more')); ?>" title="<?php echo $e(__('search.load_more')); ?>" hidden>
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/></svg>
    </button>
    <template id="pw-search-result-template"><?php echo $resultTemplate; ?></template>
</div>
