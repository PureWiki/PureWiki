<?php
/**
 * PureWiki - Global Settings View
 *
 * Configuration interface for system-wide settings
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

require_once __DIR__ . '/../core/i18n.php';
require_once __DIR__ . '/../core/asset_manager.php';
require_once __DIR__ . '/../core/misc.php';

// Extension Tabs
$extensionTabs = ExtensionLoader::applyFilter('settings.tabs', []);

// Settings Helpers

/**
 * Renders a HTML input Settings field
 */
function renderInput($id, $label, $desc, $type = 'text', $class = 'pw-input pw-w-lg', $placeholder = '', $extraAttrs = '', $descClass = 'pw-setting-description', $extraHtml = '') {
    echo '<div class="pw-setting-field">';
    echo '<label for="'.$id.'" class="pw-setting-label">'.htmlspecialchars($label).'</label>';
    if ($desc) echo '<p class="'.$descClass.'">'.$desc.'</p>';
    echo '<input type="'.$type.'" id="'.$id.'" class="'.$class.'" placeholder="'.htmlspecialchars($placeholder).'" '.$extraAttrs.'>';
    if ($extraHtml) echo $extraHtml;
    echo '</div>';
}

/**
 * Renders a HTML toggle Settings field
 */
function renderToggle($id, $label, $desc = '', $descClass = 'pw-hint', $extraHtml = '') {
    echo '<div class="pw-setting-field">';
    echo '<label class="pw-toggle-label"><input type="checkbox" id="'.$id.'" class="pw-checkbox"> '.htmlspecialchars($label).'</label>';
    if ($desc) echo '<p class="'.$descClass.'">'.$desc.'</p>';
    if ($extraHtml) echo $extraHtml;
    echo '</div>';
}

/**
 * Renders a HTML textarea Settings field
 */
function renderTextarea($id, $label, $desc, $rows = 4, $placeholder = '', $descClass = 'pw-hint-compact') {
    echo '<div class="pw-setting-field">';
    echo '<label for="'.$id.'" class="pw-setting-label">'.htmlspecialchars($label).'</label>';
    if ($desc) echo '<p class="'.$descClass.'">'.$desc.'</p>';
    echo '<textarea id="'.$id.'" class="pw-input pw-font-mono" rows="'.$rows.'" placeholder="'.htmlspecialchars($placeholder).'"></textarea>';
    echo '</div>';
}

/**
 * Renders a HTML select Settings field
 */
function renderSelect($id, $label, $desc, $options = [], $class = 'pw-input pw-w-md', $extraAttrs = '', $descClass = 'pw-setting-description') {
    echo '<div class="pw-setting-field">';
    echo '<label for="'.$id.'" class="pw-setting-label">'.htmlspecialchars($label).'</label>';
    if ($desc) echo '<p class="'.$descClass.'">'.$desc.'</p>';
    echo '<select id="'.$id.'" class="'.$class.'" '.$extraAttrs.'>';
    foreach ($options as $val => $text) {
        echo '<option value="'.htmlspecialchars($val).'">'.htmlspecialchars($text).'</option>';
    }
    echo '</select>';
    echo '</div>';
}

$pageTitle = __('settings.title') . ' - PureWiki';
require_once __DIR__ . '/layout_head.php';
?>
<body class="pw-dashboard-body">

    <!-- Header -->
    <header class="pw-dashboard-header">
        <div class="pw-header-left">
            <h1 class="pw-site-title"><?php echo __('settings.title'); ?></h1>
        </div>
        <div class="pw-header-right">
            <button class="pw-btn" onclick="window.location.href=window.PW_BASE_PATH+'/dashboard'"><iconify-icon icon="mdi:arrow-left"></iconify-icon> <?php echo __('settings.back_to_dashboard'); ?></button>
            <button id="pw-btn-save-settings" class="pw-btn pw-btn-primary"><iconify-icon icon="mdi:content-save"></iconify-icon> <?php echo __('settings.save_settings'); ?></button>
        </div>
    </header>

    <div class="pw-dashboard-container">
        <!-- Sidebar -->
        <aside class="pw-dashboard-sidebar">
            <div class="pw-treeview">
                <ul>
                    <!-- Settings Categories -->
                    <li class="pw-tree-node">
                        <div class="pw-tree-item pw-tree-active" data-settings-tab="general">
                            <span class="pw-tree-label"><iconify-icon icon="mdi:cogs" class="pw-icon-left"></iconify-icon> <?php echo __('settings.general'); ?></span>
                        </div>
                    </li>
                    <li class="pw-tree-node">
                        <div class="pw-tree-item" data-settings-tab="appearance">
                            <span class="pw-tree-label"><iconify-icon icon="mdi:palette" class="pw-icon-left"></iconify-icon> <?php echo __('settings.appearance'); ?></span>
                        </div>
                    </li>
                    <li class="pw-tree-node">
                        <div class="pw-tree-item" data-settings-tab="seo">
                            <span class="pw-tree-label"><iconify-icon icon="mdi:search-web" class="pw-icon-left"></iconify-icon> <?php echo __('settings.seo'); ?></span>
                        </div>
                    </li>
                    <li class="pw-tree-node">
                        <div class="pw-tree-item" data-settings-tab="editor">
                            <span class="pw-tree-label"><iconify-icon icon="mdi:pencil-outline" class="pw-icon-left"></iconify-icon> <?php echo __('settings.editor_tab'); ?></span>
                        </div>
                    </li>
                    <li class="pw-tree-node">
                        <div class="pw-tree-item" data-settings-tab="users">
                            <span class="pw-tree-label"><iconify-icon icon="mdi:account-group" class="pw-icon-left"></iconify-icon> <?php echo __('settings.user_management'); ?></span>
                        </div>
                    </li>
                    <li class="pw-tree-node">
                        <div class="pw-tree-item" data-settings-tab="mail">
                            <span class="pw-tree-label"><iconify-icon icon="mdi:email-outline" class="pw-icon-left"></iconify-icon> <?php echo __('settings.mail_tab'); ?></span>
                        </div>
                    </li>
                    <li class="pw-tree-node">
                        <div class="pw-tree-item" data-settings-tab="backup">
                            <span class="pw-tree-label"><iconify-icon icon="mdi:backup-restore" class="pw-icon-left"></iconify-icon> <?php echo __('settings.backup'); ?></span>
                        </div>
                    </li>
                    <li class="pw-tree-node">
                        <div class="pw-tree-item" data-settings-tab="dev_options">
                            <span class="pw-tree-label"><iconify-icon icon="mdi:bug" class="pw-icon-left"></iconify-icon> <?php echo __('settings.dev_options_tab'); ?></span>
                        </div>
                    </li>
                    <li class="pw-tree-node">
                        <div class="pw-tree-item" data-settings-tab="status">
                            <span class="pw-tree-label"><iconify-icon icon="mdi:information-outline" class="pw-icon-left"></iconify-icon> <?php echo __('settings.status'); ?></span>
                        </div>
                    </li>
                    <li class="pw-tree-node">
                        <div class="pw-tree-item" data-settings-tab="extensions">
                            <span class="pw-tree-label">
                                <iconify-icon icon="mdi:puzzle-outline" class="pw-icon-left"></iconify-icon>
                                <?php echo __('settings.extensions_tab'); ?>
                            </span>
                        </div>
                    </li>

                    <!-- Extension Tabs -->
                    <?php foreach ($extensionTabs as $tab): ?>
                        <li class="pw-tree-node">
                            <div class="pw-tree-item" data-settings-tab="<?php echo htmlspecialchars($tab['id']); ?>">
                                <span class="pw-tree-label">
                                    <iconify-icon icon="<?php echo htmlspecialchars($tab['icon'] ?? 'mdi:puzzle-outline'); ?>" class="pw-icon-left"></iconify-icon>
                                    <?php echo htmlspecialchars($tab['label']); ?>
                                </span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="pw-dashboard-main">
            <!-- General Tab Content -->
            <div id="pw-tab-general" class="pw-settings-tab" style="display: block;">
                <h2><?php echo __('settings.general_settings'); ?></h2>
                <div class="pw-settings-panel">
                    <?php
                    renderInput('pw-setting-wiki-name', __('settings.wiki_name'), __('settings.wiki_name_desc'), 'text', 'pw-input pw-w-lg', __('settings.wiki_name_placeholder'));

                    renderSelect('pw-setting-dashboard-language', __('settings.dashboard_language'), __('settings.dashboard_language_desc'), ['en' => 'English', 'de' => 'Deutsch']);

                    renderInput('pw-setting-wiki-logo', __('settings.wiki_logo'), __('settings.wiki_logo_desc'), 'text', 'pw-input pw-w-lg', __('settings.wiki_logo_placeholder'));

                    renderInput('pw-setting-wiki-favicon', __('settings.wiki_favicon'), __('settings.wiki_favicon_desc'), 'text', 'pw-input pw-w-lg', __('settings.wiki_favicon_placeholder'));

                    $themesRootDir = realpath(__DIR__ . '/../../themes');
                    $themeDirs = is_dir($themesRootDir) ? array_filter(glob($themesRootDir . '/*'), 'is_dir') : [];
                    $themeOptions = [];
                    foreach ($themeDirs as $dir) {
                        $name = basename($dir);
                        $label = prepareTitle($name);
                        $themeOptions[$name] = $label;
                    }
                    renderSelect('pw-setting-current-theme', __('settings.current_theme'), __('settings.current_theme_desc'), $themeOptions);

                    renderInput('pw-setting-allowed-extensions', __('settings.allowed_extensions'), __('settings.allowed_extensions_desc'), 'text', 'pw-input pw-w-full', __('settings.allowed_extensions_placeholder'));
                    ?>

                    <hr class="pw-separator">
                    <h3 class="pw-settings-heading"><?php echo __('settings.i18n_title'); ?></h3>
                    <?php
                    renderToggle('pw-setting-i18n-enabled', __('settings.i18n_enabled'), __('settings.i18n_enabled_desc'));
                    renderInput('pw-setting-i18n-default-lang', __('settings.i18n_default_lang'), __('settings.i18n_default_lang_desc'), 'text', 'pw-input pw-w-sm', 'de');
                    ?>
                    <div class="pw-setting-field" id="pw-setting-i18n-group">
                        <label class="pw-setting-label"><?php echo __('settings.i18n_supported_langs'); ?></label>
                        <p class="pw-setting-description"><?php echo __('settings.i18n_supported_langs_desc'); ?></p>
                        <input type="hidden" id="pw-setting-i18n-supported-langs">
                        <div id="pw-i18n-lang-list" style="display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 8px;"></div>
                        <div style="display: flex; gap: 8px;">
                            <input type="text" id="pw-i18n-new-lang" class="pw-input pw-w-sm" placeholder="e.g. en" maxlength="5">
                            <button type="button" id="pw-btn-add-lang" class="pw-btn pw-btn-secondary"><iconify-icon icon="mdi:plus"></iconify-icon> <?php echo __('common.add'); ?></button>
                        </div>
                    </div>

                    <hr class="pw-separator">
                    <h3 class="pw-settings-heading"><?php echo __('settings.caching'); ?></h3>
                    <?php
                    $clearCacheBtn = '<div style="margin-top: 15px;"><button id="pw-btn-clear-cache" class="pw-btn pw-btn-secondary" type="button"><iconify-icon icon="mdi:delete-sweep" class="pw-icon-left"></iconify-icon> '.__('settings.clear_cache').'</button></div>';

                    renderToggle('pw-setting-enable-cache', __('settings.enable_caching'), __('settings.enable_caching_desc'));

                    $cacheOptions = [
                        '3600' => __('settings.1_hr'),
                        '21600' => __('settings.6_hr'),
                        '43200' => __('settings.12_hr'),
                        '86400' => __('settings.1_day'),
                        '604800' => __('settings.1_week')
                    ];
                    renderSelect('pw-setting-cache-lifetime', __('settings.cache_lifetime'), '', $cacheOptions, 'pw-input pw-w-md', 'style="opacity: 0.5;" disabled', 'pw-setting-description');

                    echo '<div class="pw-setting-field">' . $clearCacheBtn . '</div>';
                    ?>

                    <hr class="pw-separator">

                    <h3 class="pw-settings-heading"><?php echo __('settings.page_history'); ?></h3>
                    <?php
                    renderToggle('pw-setting-enable-history', __('settings.enable_history'), __('settings.enable_history_desc'));

                    renderInput('pw-setting-history-max-versions', __('settings.max_versions'), __('settings.max_versions_desc'), 'number', 'pw-input pw-w-sm', '20', 'min="5" max="50" value="20" style="opacity: 0.5;" disabled', 'pw-hint-compact');
                    ?>

                    <hr class="pw-separator">

                    <h3 class="pw-settings-heading"><?php echo __('settings.search'); ?></h3>
                    <?php
                    renderInput('pw-setting-search-max-results', __('settings.max_results'), __('settings.max_results_desc'), 'number', 'pw-input pw-w-sm', '10', 'min="1" max="100" value="10"', 'pw-setting-description');
                    ?>
                </div>
            </div>
            <!-- Appearance Tab Content -->
            <div id="pw-tab-appearance" class="pw-settings-tab" style="display: none;">
                <h2><?php echo __('settings.appearance_title'); ?></h2>

                <div class="pw-settings-panel">
                    <?php
                    renderSelect('pw-setting-dashboard-theme', __('settings.dashboard_theme'), __('settings.dashboard_theme_desc'), ['dark' => __('settings.theme_dark'), 'light' => __('settings.theme_light')]);

                    renderToggle('pw-setting-breadcrumbs-show-start-page', __('settings.breadcrumbs_show_start'), __('settings.breadcrumbs_show_start_desc'));
                    ?>

                    <hr class="pw-separator">

                    <?php
                    renderToggle('pw-setting-enable-admin-menu', __('settings.enable_admin_menu'), __('settings.enable_admin_menu_desc'));
                    ?>

                    <hr class="pw-separator">

                    <h3 class="pw-settings-heading"><?php echo __('settings.custom_code'); ?></h3>
                    <p class="pw-hint"><?php echo __('settings.custom_code_desc'); ?></p>

                    <?php
                    renderTextarea('pw-setting-custom-css', __('settings.custom_css'), __('settings.custom_css_desc'), 4, __('settings.custom_css_placeholder'));

                    renderTextarea('pw-setting-custom-html-head', __('settings.custom_html_head'), __('settings.custom_html_head_desc'), 3, __('settings.custom_html_head_placeholder'));

                    renderTextarea('pw-setting-custom-html-footer', __('settings.custom_html_footer'), __('settings.custom_html_footer_desc'), 3, __('settings.custom_html_footer_placeholder'));

                    renderTextarea('pw-setting-custom-js-head', __('settings.custom_js_head'), __('settings.custom_js_head_desc'), 4, __('settings.custom_js_head_placeholder'));

                    renderTextarea('pw-setting-custom-js-footer', __('settings.custom_js_footer'), __('settings.custom_js_footer_desc'), 4, __('settings.custom_js_footer_placeholder'));
                    ?>
                </div>
            </div>

            <!-- SEO Tab Content -->
            <div id="pw-tab-seo" class="pw-settings-tab" style="display: none;">
                <h2><?php echo __('settings.seo_title'); ?></h2>

                <div class="pw-settings-panel">
                    <?php
                    renderToggle('pw-setting-seo-prevent-index', __('settings.seo_prevent_index'), __('settings.seo_prevent_index_desc'));

                    renderToggle('pw-setting-seo-sitemap', __('settings.seo_sitemap'), __('settings.seo_sitemap_desc'));

                    renderInput('pw-setting-wiki-description', __('settings.seo_fallback_description'), __('settings.seo_fallback_description_desc'), 'text', 'pw-input pw-w-full', __('settings.seo_fallback_description_placeholder'));

                    renderInput('pw-setting-seo-title-format', __('settings.seo_title_format'), __('settings.seo_title_format_desc'), 'text', 'pw-input pw-w-lg', __('settings.seo_title_format_placeholder'));
                    ?>

                    <hr class="pw-separator">
                    <h3 class="pw-settings-heading"><?php echo __('settings.meta_data_generation'); ?></h3>

                    <?php
                    renderToggle('pw-setting-seo-auto-canonical', __('settings.seo_auto_canonical'), __('settings.seo_auto_canonical_desc'));

                    renderToggle('pw-setting-seo-opengraph', __('settings.seo_opengraph'), __('settings.seo_opengraph_desc'));

                    renderToggle('pw-setting-seo-twitter', __('settings.seo_twitter'), __('settings.seo_twitter_desc'));

                    renderToggle('pw-setting-seo-schema-org', __('settings.seo_schema_org'), __('settings.seo_schema_org_desc'));

                    renderInput('pw-setting-seo-og-image', __('settings.seo_og_image'), __('settings.seo_og_image_desc'), 'text', 'pw-input pw-w-lg', __('settings.seo_og_image_placeholder'), '', 'pw-hint-compact');
                    ?>
                </div>
            </div>

            <!-- Editor Tab Content -->
            <div id="pw-tab-editor" class="pw-settings-tab" style="display: none;">
                <h2><?php echo __('settings.editor_tab'); ?></h2>
                <div class="pw-settings-panel" style="gap: 10px;">
                    <h3 class="pw-settings-heading"><?php echo __('settings.editor_blocks_visibility'); ?></h3>
                    <p class="pw-hint"><?php echo __('settings.editor_blocks_visibility_desc'); ?></p>

                    <?php
                    renderToggle('pw-setting-editor-show-raw', __('settings.editor_show_raw'));
                    renderToggle('pw-setting-editor-show-markdown', __('settings.editor_show_markdown'));
                    renderToggle('pw-setting-editor-show-liveMarkdown', __('settings.editor_show_liveMarkdown'));
                    renderToggle('pw-setting-editor-show-code', __('settings.editor_show_code'));
                    renderToggle('pw-setting-editor-show-table', __('settings.editor_show_table'));
                    renderToggle('pw-setting-editor-show-pagelist', __('settings.editor_show_pagelist'));
                    renderToggle('pw-setting-editor-show-toc', __('settings.editor_show_toc'));
                    renderToggle('pw-setting-editor-show-callout', __('settings.editor_show_callout'));
                    renderToggle('pw-setting-editor-show-block', __('settings.editor_show_block'));
                    renderToggle('pw-setting-editor-show-pageinclude', __('settings.editor_show_pageinclude'));
                    renderToggle('pw-setting-editor-show-snippet', __('settings.editor_show_snippet'));
                    renderToggle('pw-setting-editor-show-accordion', __('settings.editor_show_accordion'));
                    renderToggle('pw-setting-editor-show-grid', __('settings.editor_show_grid'));
                    renderToggle('pw-setting-editor-show-math', __('settings.editor_show_math'));
                    ?>
                </div>
            </div>

            <!-- Backup Tab Content -->
            <div id="pw-tab-backup" class="pw-settings-tab" style="display: none;">
                <h2><?php echo __('settings.backup_title'); ?></h2>

                <div class="pw-settings-panel">
                    <div>
                        <h3 class="pw-settings-heading"><?php echo __('settings.manual_backup'); ?></h3>
                        <p class="pw-hint"><?php echo __('settings.manual_backup_desc'); ?></p>
                        <button id="pw-btn-start-backup" class="pw-btn pw-btn-primary"><iconify-icon icon="mdi:zip-box-outline" class="pw-icon-left"></iconify-icon> <?php echo __('settings.start_backup'); ?></button>
                    </div>

                    <hr class="pw-separator">

                    <div>
                        <h3 class="pw-settings-subheading"><?php echo __('settings.available_backups'); ?></h3>
                        <table class="pw-settings-table">
                            <thead>
                                <tr>
                                    <th><?php echo __('settings.file_date'); ?></th>
                                    <th><?php echo __('settings.file_size'); ?></th>
                                    <th class="pw-text-right"><?php echo __('settings.actions'); ?></th>
                                </tr>
                            </thead>
                            <tbody id="pw-backup-list">
                                <tr><td colspan="3" style="padding: 12px; color: var(--pw-text-muted);"><?php echo __('settings.loading_backups'); ?></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Mail Tab Content -->
            <div id="pw-tab-mail" class="pw-settings-tab" style="display: none;">
                <h2><?php echo __('settings.mail_title'); ?></h2>
                <div class="pw-settings-panel">
                    <?php
                    renderToggle('pw-setting-mail-enable', __('settings.mail_enable'), __('settings.mail_enable_desc'));
                    ?>

                    <form id="pw-form-mail" onsubmit="return false;">
                        <div id="pw-mail-settings-group" style="margin-top: 15px;">
                            <hr class="pw-separator">
                            <h3 class="pw-settings-heading"><?php echo __('settings.smtp_configuration'); ?></h3>

                            <?php
                            renderInput('pw-setting-mail-host', __('settings.mail_host'), '', 'text', 'pw-input pw-w-lg', 'smtp.example.com');
                            renderInput('pw-setting-mail-port', __('settings.mail_port'), '', 'number', 'pw-input pw-w-sm', '587');
                            renderInput('pw-setting-mail-username', __('settings.mail_username'), '', 'text', 'pw-input pw-w-lg', 'user@example.com', 'autocomplete="username"');
                            renderInput('pw-setting-mail-password', __('settings.mail_password'), __('settings.mail_password_desc'), 'password', 'pw-input pw-w-lg', '••••••••', 'autocomplete="current-password"');
                            renderSelect('pw-setting-mail-encryption', __('settings.mail_encryption'), '', ['none' => __('settings.mail_enc_none'), 'tls' => 'TLS', 'ssl' => 'SSL'], 'pw-input pw-w-sm');
                            renderInput('pw-setting-mail-from-address', __('settings.mail_from_address'), '', 'email', 'pw-input pw-w-lg', 'no-reply@example.com');
                            renderInput('pw-setting-mail-from-name', __('settings.mail_from_name'), '', 'text', 'pw-input pw-w-lg', 'PureWiki');
                            ?>

                            <hr class="pw-separator">
                            <h3 class="pw-settings-heading"><?php echo __('settings.test_mail'); ?></h3>
                            <p class="pw-hint"><?php echo __('settings.test_mail_desc'); ?></p>
                            <div style="display: flex; gap: 10px; align-items: center; margin-top: 10px;">
                                <input type="email" id="pw-setting-test-mail-address" class="pw-input pw-w-lg" placeholder="test@example.com">
                                <button type="button" id="pw-btn-send-test-mail" class="pw-btn pw-btn-secondary"><iconify-icon icon="mdi:send" class="pw-icon-left"></iconify-icon> <?php echo __('settings.send_test_mail'); ?></button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- User Management Tab Content -->
            <div id="pw-tab-users" class="pw-settings-tab" style="display: none;">
                <h2><?php echo __('settings.users_title'); ?></h2>

                <!-- User List -->
                <div class="pw-settings-panel" style="gap: 0;">
                    <h3 class="pw-settings-subheading"><?php echo __('settings.users_heading'); ?></h3>
                    <table class="pw-settings-table">
                        <thead>
                            <tr>
                                <th><?php echo __('settings.username'); ?></th>
                                <th><?php echo __('settings.role'); ?></th>
                                <th class="pw-text-right"><?php echo __('settings.actions'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="pw-user-list">
                            <!-- Placeholder users -->
                            <tr>
                                <td>admin</td>
                                <td>admin</td>
                                <td class="pw-text-right">
                                    <button class="pw-btn pw-btn-danger pw-btn-sm" disabled title="<?php echo __('settings.cannot_delete_last'); ?>" aria-label="<?php echo __('settings.cannot_delete_last'); ?>"><iconify-icon icon="mdi:delete-outline"></iconify-icon></button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Add User Form -->
                <div class="pw-settings-panel" style="gap: 0;">
                    <h3 class="pw-settings-subheading"><?php echo __('settings.add_user'); ?></h3>
                    <form id="pw-form-add-user" class="pw-form-stack">
                        <div>
                            <label for="pw-new-username" class="pw-setting-label"><?php echo __('settings.username'); ?></label>
                            <input type="text" id="pw-new-username" name="username" class="pw-input pw-w-full" placeholder="<?php echo __('settings.username_placeholder'); ?>" autocomplete="username">
                        </div>
                        <div>
                            <label for="pw-new-password" class="pw-setting-label"><?php echo __('settings.password_label'); ?></label>
                            <input type="password" id="pw-new-password" name="password" class="pw-input pw-w-full" placeholder="<?php echo __('settings.password_placeholder'); ?>" autocomplete="new-password">
                        </div>
                        <div>
                            <label for="pw-new-role" class="pw-setting-label"><?php echo __('settings.role'); ?></label>
                            <select id="pw-new-role" name="role" class="pw-input pw-w-full">
                                <option value="admin">Admin</option>
                                <option value="editor">Editor</option>
                                <option value="reader">Reader</option>
                            </select>
                        </div>
                        <div>
                            <button type="submit" id="pw-btn-add-user" class="pw-btn pw-btn-primary" style="margin-top: 4px;"><iconify-icon icon="mdi:account-plus"></iconify-icon> <?php echo __('settings.add_user_btn'); ?></button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Dev Options Tab Content -->
            <div id="pw-tab-dev_options" class="pw-settings-tab" style="display: none;">
                <h2><?php echo __('settings.dev_options_title'); ?></h2>
                <div class="pw-settings-panel">
                    <?php
                    renderToggle('pw-setting-dev-debug-output', __('settings.dev_debug_output'), __('settings.dev_debug_output_desc'));
                    ?>
                </div>

                <?php if (!empty(getGlobalConfig()['dev_debug_output'])): ?>
                <div class="pw-settings-panel" id="pw-debug-log-viewer">
                    <h3 class="pw-settings-heading">
                        <iconify-icon icon="mdi:bug-outline" class="pw-icon-left"></iconify-icon>
                        <?php echo __('settings.debug_log_viewer'); ?>
                    </h3>
                    <p class="pw-hint"><?php echo __('settings.debug_log_viewer_desc'); ?></p>

                    <div style="display: flex; gap: 10px; align-items: center; margin-bottom: 10px; flex-wrap: wrap;">
                        <select id="pw-debug-log-lines" class="pw-input pw-w-sm">
                            <option value="50">50</option>
                            <option value="100">100</option>
                            <option value="200" selected>200</option>
                            <option value="500">500</option>
                        </select>
                        <span class="pw-hint" style="margin: 0;"><?php echo __('settings.debug_log_lines'); ?></span>
                        <button id="pw-debug-log-refresh" class="pw-btn pw-btn-secondary" type="button">
                            <iconify-icon icon="mdi:refresh" class="pw-icon-left"></iconify-icon>
                            <?php echo __('settings.debug_log_refresh'); ?>
                        </button>
                        <button id="pw-debug-log-clear" class="pw-btn pw-btn-danger" type="button">
                            <iconify-icon icon="mdi:delete-outline" class="pw-icon-left"></iconify-icon>
                            <?php echo __('settings.debug_log_clear'); ?>
                        </button>
                        <span id="pw-debug-log-size-info" class="pw-hint" style="margin: 0; margin-left: auto;"></span>
                    </div>

                    <pre id="pw-debug-log-output" class="pw-debug-log-box"><?php echo __('settings.debug_log_empty'); ?></pre>
                </div>
                <?php endif; ?>
            </div>

            <!-- Status Tab Content -->
            <div id="pw-tab-status" class="pw-settings-tab" style="display: none;">
                <h2><?php echo __('settings.status_title'); ?></h2>
                <div class="pw-settings-panel">
                    <div class="pw-status-grid">
                        <div class="pw-status-grid-label"><?php echo __('settings.wiki_version'); ?></div>
                        <div id="pw-status-version"><?php echo __('common.loading'); ?></div>

                        <div class="pw-status-grid-label"><?php echo __('settings.php_version'); ?></div>
                        <div id="pw-status-php-version"><?php echo __('common.loading'); ?></div>

                        <div class="pw-status-grid-label"><?php echo __('settings.os'); ?></div>
                        <div id="pw-status-os"><?php echo __('common.loading'); ?></div>

                        <div class="pw-status-grid-label"><?php echo __('settings.webp_support'); ?></div>
                        <div id="pw-status-webp"><?php echo __('common.loading'); ?></div>

                        <div class="pw-status-grid-label"><?php echo __('settings.media_optimization'); ?></div>
                        <div id="pw-status-media-stats"><?php echo __('common.loading'); ?></div>
                    </div>

                    <div id="pw-status-actions" style="margin-top: 10px; display: none;">
                        <button id="pw-btn-bulk-webp" class="pw-btn">
                            <iconify-icon icon="mdi:auto-fix"></iconify-icon> <?php echo __('settings.optimize_all'); ?>
                        </button>
                    </div>

                    <div id="pw-status-alerts" style="margin-top: 10px;">
                        <!-- Status messages/alerts will be injected here -->
                    </div>

                    <hr class="pw-separator">

                    <div id="pw-update-section">
                        <h3 class="pw-settings-heading"><?php echo __('settings.software_update'); ?></h3>
                        <div style="margin-top: 15px;"> 
                            <?php renderToggle('pw-setting-allow-prerelease-updates', __('settings.allow_prerelease_updates'), __('settings.allow_prerelease_updates_desc')); ?>
                        </div>
                        <div id="pw-update-container" class="pw-update-card">
                            <div class="pw-update-info">
                                <p id="pw-update-status-text"><?php echo __('settings.checking_updates'); ?></p>
                                <div id="pw-update-meta" style="display: none; margin-top: 10px;">
                                    <div class="pw-update-version-badge"><?php echo __('settings.new_version'); ?> <span id="pw-update-new-version"></span></div>
                                </div>
                            </div>
                            <div class="pw-update-actions" style="margin-top: 15px;">
                                <button id="pw-btn-check-updates" class="pw-btn">
                                    <iconify-icon icon="mdi:refresh"></iconify-icon> <?php echo __('settings.check_updates'); ?>
                                </button>
                                <button id="pw-btn-view-releasenotes" class="pw-btn pw-btn-secondary" style="display: none;" type="button">
                                    <iconify-icon icon="mdi:text-box-outline"></iconify-icon> <?php echo __('settings.view_releasenotes'); ?>
                                </button>
                                <a id="pw-link-changelog" href="https://github.com/PureWiki/PureWiki/releases" class="pw-btn pw-btn-secondary" style="display: none; text-decoration: none; align-items: center; justify-content: center;" target="_blank">
                                    <iconify-icon icon="mdi:github"></iconify-icon> <?php echo __('settings.open_changelog'); ?>
                                </a>
                                <button id="pw-btn-start-update" class="pw-btn pw-btn-primary" style="display: none;">
                                    <iconify-icon icon="mdi:cloud-download"></iconify-icon> <?php echo __('settings.update_now'); ?>
                                </button>
                            </div>
                        </div>

                        <!-- Update Progress Overlay (hidden by default) -->
                        <div id="pw-update-progress-overlay" style="display: none; margin-top: 20px; padding: 20px; background: rgba(0,0,0,0.2); border-radius: 8px;">
                            <h4 id="pw-update-step-title"><?php echo __('settings.preparing_update'); ?></h4>
                            <div class="pw-progress-wrapper" style="margin: 15px 0;">
                                <progress id="pw-update-progress-bar" value="0" max="100" style="width: 100%; height: 10px;"></progress>
                            </div>
                            <p id="pw-update-step-desc" class="pw-hint"></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Extensions Tab Content -->
            <div id="pw-tab-extensions" class="pw-settings-tab" style="display: none;">
                <h2><?php echo __('settings.extensions_title'); ?></h2>
                <div class="pw-settings-panel">
                    <p class="pw-hint"><?php echo __('settings.extensions_desc'); ?></p>

                    <div id="pw-extensions-list" style="display: flex; flex-direction: column; gap: 15px; margin-top: 15px;">
                        <div><?php echo __('common.loading'); ?></div>
                    </div>
                </div>
            </div>

            <!-- Extension Registered Tabs (Content) -->
            <?php foreach ($extensionTabs as $tab): ?>
                <div id="pw-tab-<?php echo htmlspecialchars($tab['id']); ?>" class="pw-settings-tab" style="display: none;">
                    <?php 
                    if (isset($tab['file']) && file_exists($tab['file'])) {
                        include $tab['file'];
                    }
                    ?>
                </div>
            <?php endforeach; ?>
        </main>
    </div>

    <!-- Scripts -->
    <?php echo getLanguageScript(); ?>
    <script src="<?php echo BASE_PATH; ?>/purewiki/assets/js/i18n.js"></script>
    <script src="<?php echo BASE_PATH; ?>/purewiki/assets/js/core.js"></script>
    <script>window.PW_DEBUG = <?php echo !empty(getGlobalConfig()['dev_debug_output']) ? 'true' : 'false'; ?>;</script>
    <script src="<?php echo BASE_PATH; ?>/purewiki/assets/js/notify.js"></script>
    <script src="<?php echo BASE_PATH; ?>/purewiki/assets/js/settings.js"></script>
    <script src="<?php echo BASE_PATH; ?>/purewiki/assets/js/clear_cache.js"></script>

    <!-- Templates (used by settings.js) -->
    <template id="tpl-user-row">
        <tr>
            <td data-field="username"></td>
            <td><span style="text-transform: capitalize;" data-field="role"></span></td>
            <td class="pw-text-right">
                <button class="pw-btn pw-btn-danger pw-btn-sm pw-user-delete-btn" aria-label="<?php echo __('common.delete'); ?>" title="<?php echo __('common.delete'); ?>">
                    <iconify-icon icon="mdi:delete-outline"></iconify-icon>
                </button>
            </td>
        </tr>
    </template>

    <template id="tpl-backup-row">
        <tr>
            <td data-field="date"></td>
            <td data-field="size"></td>
            <td class="pw-text-right">
                <a class="pw-btn pw-btn-secondary pw-btn-sm pw-backup-download-link" style="text-decoration: none; display: inline-flex; align-items: center; justify-content: center; margin-right: 5px;" title="Download" target="_blank">
                    <iconify-icon icon="mdi:download-outline"></iconify-icon> <span data-field="download-label"></span>
                </a>
                <button class="pw-btn pw-btn-danger pw-btn-sm pw-backup-delete-btn" title="<?php echo __('settings.delete_backup'); ?>" aria-label="<?php echo __('settings.delete_backup'); ?>">
                    <iconify-icon icon="mdi:delete-outline"></iconify-icon>
                </button>
            </td>
        </tr>
    </template>

    <template id="tpl-webp-alert">
        <div style="padding: 12px; background: rgba(244, 67, 54, 0.1); border-left: 4px solid #f44336; border-radius: 4px; color: #d32f2f; font-size: 0.9em; margin-bottom: 10px;">
            <strong><iconify-icon icon="mdi:information-outline"></iconify-icon> Info:</strong> <span data-field="webp-alert-text"></span>
        </div>
    </template>

    <template id="tpl-security-alert">
        <div style="padding: 12px; background: rgba(255, 0, 0, 0.1); border-left: 4px solid var(--pw-danger); border-radius: 4px; color: var(--pw-text); font-size: 0.9em; margin-bottom: 10px;">
            <strong style="display: flex; align-items: center; gap: 4px; margin-bottom: 4px;">
                <iconify-icon icon="mdi:alert"></iconify-icon> <span data-field="security-alert-title"></span>
            </strong>
            <span data-field="security-alert-text"></span>
        </div>
    </template>

    <template id="tpl-media-stats">
        <div>
            <div style="display: flex; align-items: center; gap: 10px;">
                <progress data-field="progress" value="0" max="100" style="flex-grow: 1; height: 8px;"></progress>
                <span style="font-size: 0.9em; font-weight: 600;" data-field="ratio"></span>
            </div>
            <div style="font-size: 0.8em; color: var(--pw-text-muted); margin-top: 4px;" data-field="summary"></div>
        </div>
    </template>

    <template id="tpl-extension-card">
        <div class="pw-extension-card" style="border: 1px solid var(--pw-border); border-radius: 8px; padding: 15px; background: var(--pw-bg);">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;">
                <div>
                    <h3 style="margin: 0 0 5px 0; display: flex; align-items: center; gap: 8px;">
                        <span data-field="name"></span>
                        <span class="pw-badge" data-field="status-badge" style="font-size: 0.75em; padding: 2px 6px; border-radius: 4px;"></span>
                    </h3>
                    <div style="font-size: 0.85em; color: var(--pw-text-muted);">
                        v<span data-field="version"></span> | <?php echo __('settings.by'); ?> <span data-field="author"></span>
                    </div>
                </div>
                <div style="display: flex; gap: 8px;">
                    <button class="pw-btn pw-btn-sm pw-btn-ext-toggle" data-action="toggle"></button>
                    <button class="pw-btn pw-btn-danger pw-btn-sm pw-btn-ext-uninstall" data-action="uninstall" title="<?php echo __('common.delete'); ?>">
                        <iconify-icon icon="mdi:delete-outline"></iconify-icon>
                    </button>
                </div>
            </div>
            <p style="margin: 0 0 10px 0; font-size: 0.9em;" data-field="description"></p>
            <div style="font-size: 0.85em;">
                <a href="#" data-field="url" target="_blank" style="color: var(--pw-primary); text-decoration: none;">
                    <iconify-icon icon="mdi:open-in-new"></iconify-icon> <?php echo __('settings.visit_website'); ?>
                </a>
            </div>
        </div>
    </template>

</body>
</html>
