<?php
/**
 * PureWiki - Frontend Macro: Search
 *
 * Renders the search toggle button and dropdown for the frontend header.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

require_once __DIR__ . '/../../core/i18n.php';

$searchPageUrl = getSearchPageUrl($config);
?>
<li class="pw-search-wrapper">
    <button class="pw-search-toggle" id="pw-search-toggle" aria-label="Search">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
    </button>
    <div class="pw-search-dropdown" id="pw-search-dropdown"
         data-text-no-results="<?php echo htmlspecialchars(__('search.no_results')); ?>"
         data-text-failed="<?php echo htmlspecialchars(__('search.failed')); ?>"
         <?php if ($searchPageUrl !== ''): ?>data-search-url="<?php echo htmlspecialchars($searchPageUrl); ?>"<?php endif; ?>>
        <input type="text" id="pw-search-input" placeholder="<?php echo htmlspecialchars(__('search.placeholder')); ?>" aria-label="<?php echo htmlspecialchars(__('search.title')); ?>" autocomplete="off">
        <div class="pw-search-results" id="pw-search-results"></div>
    </div>
</li>
