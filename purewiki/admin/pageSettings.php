<?php
/**
 * PureWiki - Page Settings View
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/i18n.php';
require_once __DIR__ . '/../core/asset_manager.php';
require_once __DIR__ . '/../core/misc.php';

$pagePath  = $_GET['path'] ?? '/';
$pageLang  = $_GET['lang'] ?? '';
$pageTitle = __('editor.page_settings_title') . ' - PureWiki';
$config    = getGlobalConfig();
$extraCss  = [BASE_PATH . '/purewiki/assets/css/editor.css'];
require_once __DIR__ . '/layout_head.php';
?>
<body class="pw-dashboard-body">

    <!-- Header -->
    <header class="pw-dashboard-header">
        <div class="pw-header-left">
             <h1 class="pw-site-title" id="pw-page-settings-title"><?php echo __('editor.page_settings_title'); ?></h1>
             <span id="pw-ps-path-label" style="margin-left: 15px; font-size: 0.9em; opacity: 0.7; font-family: monospace;" data-path="<?php echo htmlspecialchars($pagePath); ?>"><?php echo htmlspecialchars($pagePath); ?></span>
        </div>
        <div class="pw-edit-header-center">
            <?php if (!empty($config['i18n_enabled'])):
                $defLang   = $config['i18n_default_lang'] ?? 'de';
                $suppLangs = $config['i18n_supported_langs'] ?? [];
                // Determine display label for the initially selected language
                $initLangDisplay = empty($pageLang)
                    ? 'Default (' . $defLang . ')'
                    : htmlspecialchars($pageLang);
            ?>
            <div class="pw-lang-switcher pw-history-dropdown" id="pw-lang-switcher-container" style="margin-right: 1rem;">
                <button id="pw-btn-lang" class="pw-btn" title="<?php echo __('editor.language'); ?>" aria-label="<?php echo __('editor.language'); ?>">
                    <iconify-icon icon="mdi:translate"></iconify-icon>
                    <span id="pw-lang-label" data-lang="<?php echo htmlspecialchars($pageLang); ?>" style="font-weight: 600; min-width: 20px; text-align: center; display: inline-block;">
                        <?php echo $initLangDisplay; ?>
                    </span>
                    <iconify-icon icon="mdi:chevron-down" style="margin-left: 2px;"></iconify-icon>
                </button>
                <div id="pw-lang-menu" class="pw-history-menu" style="min-width: 160px;">
                    <button class="pw-history-item pw-lang-option" data-lang="">
                        <div style="display:flex; justify-content:space-between; width:100%; align-items:center;">
                            <span>Default (<?php echo htmlspecialchars($defLang); ?>)</span>
                        </div>
                    </button>
                    <?php foreach ($suppLangs as $sl): ?>
                    <button class="pw-history-item pw-lang-option" data-lang="<?php echo htmlspecialchars($sl); ?>">
                        <div style="display:flex; justify-content:space-between; width:100%; align-items:center;">
                            <span><?php echo htmlspecialchars($sl); ?></span>
                        </div>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <div class="pw-header-right">
            <button id="pw-btn-back" class="pw-btn"><iconify-icon icon="mdi:arrow-left"></iconify-icon> <?php echo __('common.back'); ?></button>
            <button id="pw-btn-view-code" class="pw-btn"><iconify-icon icon="mdi:code-json"></iconify-icon> <?php echo __('editor.view_code'); ?></button>
            <button id="pw-btn-save-page-settings" class="pw-btn pw-btn-primary"><iconify-icon icon="mdi:content-save"></iconify-icon> <?php echo __('common.save'); ?></button>
        </div>
    </header>

    <div class="pw-dashboard-container">
        <!-- Main Content -->
        <main class="pw-dashboard-main pw-w-full">
            <div class="pw-settings-panel pw-w-xlg" style="margin: 0 auto;">
               
                <!-- Description -->
                <div class="pw-setting-field">
                    <label for="ps-description" class="pw-setting-label"><?php echo __('editor.description_label'); ?></label>
                    <p class="pw-setting-description"><?php echo __('editor.description_hint'); ?></p>
                    <textarea id="ps-description" class="pw-input pw-w-full" rows="3" placeholder="<?php echo __('editor.description_placeholder'); ?>"></textarea>
                </div>

                <!-- Tags -->
                <div class="pw-setting-field">
                    <label for="ps-tags" class="pw-setting-label"><?php echo __('editor.tags_label'); ?></label>
                    <p class="pw-setting-description"><?php echo __('editor.tags_hint'); ?></p>
                    <input type="text" id="ps-tags" class="pw-input pw-w-full" placeholder="e.g. Doc, Wiki, Internal">
                </div>

                <hr class="pw-separator">

                <!-- Sidebar Visibility -->
                <h3 class="pw-settings-heading"><?php echo __('editor.layout_toggles'); ?></h3>
                <p class="pw-hint"><?php echo __('editor.layout_toggles_hint'); ?></p>
                <div class="pw-setting-field">
                    <label class="pw-toggle-label">
                        <input type="checkbox" id="ps-hide-left-sidebar" class="pw-checkbox">
                        <?php echo __('editor.hide_left_sidebar'); ?>
                    </label>
                    <label class="pw-toggle-label">
                        <input type="checkbox" id="ps-hide-right-sidebar" class="pw-checkbox">
                        <?php echo __('editor.hide_right_sidebar'); ?>
                    </label>
                </div>

                <hr class="pw-separator">

                <!-- Layout -->
                <h3 class="pw-settings-heading"><?php echo __('editor.layout_template'); ?></h3>
                <div class="pw-setting-field">
                    <p class="pw-hint"><?php echo __('editor.layout_template_hint'); ?></p>
                    <select id="ps-layout" class="pw-input pw-w-md">
                        <?php
                        $config = getGlobalConfig();
                        $activeTheme = $config['current_theme'] ?? 'default';
                        $themesDir = realpath(dirname(__DIR__, 2) . '/themes/' . $activeTheme);
                        $themeFiles = $themesDir ? glob($themesDir . '/*.php') : [];
                        foreach ($themeFiles as $file) {
                                $slug = pathinfo($file, PATHINFO_FILENAME);
                            $label = prepareTitle($slug);
                            echo '<option value="' . htmlspecialchars($slug) . '">' . htmlspecialchars($label) . '</option>';
                        }
                        ?>
                    </select>
                </div>

                <hr class="pw-separator">

                <!-- Visibility -->
                <h3 class="pw-settings-heading"><?php echo __('editor.visibility'); ?></h3>
                <p class="pw-hint"><?php echo __('editor.visibility_hint'); ?></p>
                <div class="pw-setting-field">
                    <label class="pw-toggle-label">
                        <input type="checkbox" id="ps-is-private" class="pw-checkbox">
                        <?php echo __('editor.private_label'); ?>
                    </label>
                    <label class="pw-toggle-label">
                        <input type="checkbox" id="ps-hide-in-treeview" class="pw-checkbox">
                        <?php echo __('editor.hide_in_treeview'); ?>
                    </label>
                </div>

                <hr class="pw-separator">

                <!-- Include in Title-Bar -->
                <h3 class="pw-settings-heading"><?php echo __('editor.navigation_bar'); ?></h3>
                <div class="pw-setting-field">
                    <p class="pw-hint"><?php echo __('editor.navbar_hint'); ?></p>
                    <label class="pw-toggle-label">
                        <input type="checkbox" id="ps-include-in-navbar" class="pw-checkbox">
                        <?php echo __('editor.include_in_navbar'); ?>
                    </label>

                    <div style="margin-top: 15px;">
                        <label for="ps-navbar-link-text" class="pw-setting-label"><?php echo __('editor.link_text_label'); ?></label>
                        <input type="text" id="ps-navbar-link-text" class="pw-input pw-w-md" placeholder="<?php echo __('editor.link_text_placeholder'); ?>">
                    </div>
                </div>

                <hr class="pw-separator">

                <!-- Next/Prev Navigation -->
                <h3 class="pw-settings-heading"><?php echo __('editor.prevnext_nav'); ?></h3>
                <div class="pw-setting-field">
                    <label class="pw-toggle-label" style="margin-bottom: 10px;">
                        <input type="checkbox" id="ps-prevnext-enable" class="pw-checkbox">
                        <?php echo __('editor.prevnext_enable'); ?>
                    </label>

                    <div id="ps-prevnext-options" style="margin-left: 24px;">
                        <p class="pw-setting-label" style="margin-bottom: 8px;"><?php echo __('editor.prevnext_scope'); ?></p>
                        <label class="pw-toggle-label" style="margin-bottom: 6px;">
                            <input type="radio" name="ps-prevnext-scope" value="siblings" checked>
                            <?php echo __('editor.prevnext_scope_siblings'); ?>
                        </label>
                        <label class="pw-toggle-label" style="margin-bottom: 6px;">
                            <input type="radio" name="ps-prevnext-scope" value="hierarchy">
                            <?php echo __('editor.prevnext_scope_hierarchy'); ?>
                        </label>
                    </div>
                </div>

                <!-- Comments -->
                <?php if (!empty($config['comments_enabled'])): ?>
                <hr class="pw-separator">
                <h3 class="pw-settings-heading"><?php echo __('comments.title'); ?></h3>
                <div class="pw-setting-field">
                    <label class="pw-toggle-label">
                        <input type="checkbox" id="ps-enable-comments" class="pw-checkbox">
                        <?php echo __('comments.enable_page'); ?>
                    </label>
                </div>
                <?php endif; ?>

                <?php
                if (class_exists('ExtensionLoader')) {
                    echo ExtensionLoader::applyFilter('page_settings.fields', '', ['path' => $pagePath]);
                }
                ?>
            </div>
        </main>
    </div>

    <!-- Scripts -->
    <?php echo getLanguageScript(); ?>
    <script src="<?php echo BASE_PATH; ?>/purewiki/assets/js/i18n.js"></script>
    <script src="<?php echo BASE_PATH; ?>/purewiki/assets/js/core.js"></script>
    <script>window.PW_DEBUG = <?php echo !empty(getGlobalConfig()['dev_debug_output']) ? 'true' : 'false'; ?>;</script>
    <script src="<?php echo BASE_PATH; ?>/purewiki/assets/js/notify.js"></script>
    <script src="<?php echo BASE_PATH; ?>/purewiki/assets/js/pageSettings.js"></script>
    
</body>
</html>
