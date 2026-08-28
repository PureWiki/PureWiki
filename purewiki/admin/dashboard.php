<?php
/**
 * PureWiki - Dashboard Main
 *
 * Primary entry point for the administration interface. Sets up the
 * dashboard layout, sidebar tree and UI components
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/tree.php';
require_once __DIR__ . '/../core/snippets.php';
require_once __DIR__ . '/../core/i18n.php';
require_once __DIR__ . '/../core/asset_manager.php';
require_once __DIR__ . '/../core/fs.php';
require_once __DIR__ . '/../core/misc.php';
require_once __DIR__ . '/../core/auth.php';

$pagesDir = getPageDir();
$tree = getPagesTree($pagesDir);
$config = getGlobalConfig();
$wikiName = $config['wiki_name'] ?? 'PureWiki';
$pageTitle = 'PureWiki - Dashboard';
require_once __DIR__ . '/layout_head.php';
?>
<body class="pw-dashboard-body">
    <!-- Header -->
    <header class="pw-dashboard-header">
        <div class="pw-header-left">
            <button id="pw-dashboard-sidebar-toggle" class="pw-btn pw-sidebar-toggle-btn" title="Toggle Sidebar" aria-label="Toggle Sidebar"><iconify-icon icon="mdi:menu"></iconify-icon></button>
            <h1 class="pw-site-title"><?php echo htmlspecialchars($wikiName); ?></h1>
        </div>
        <div class="pw-header-right">
            <?php if ( $config['enable_cache'] ) { ?>
                <button id="pw-btn-clear-cache" class="pw-btn" title="<?php echo __('dashboard.clear_cache'); ?>" aria-label="<?php echo __('dashboard.clear_cache'); ?>"><iconify-icon icon="mdi:lightning-bolt"></iconify-icon></button>
            <?php } ?>
            <button class="pw-btn" onclick="window.open(window.PW_BASE_PATH+'/', '_blank')"><iconify-icon icon="mdi:open-in-new"></iconify-icon> <?php echo __('dashboard.visit_wiki'); ?></button>
            <button class="pw-btn" onclick="window.location.href=window.PW_BASE_PATH+'/dashboard/media'"><iconify-icon icon="mdi:image-multiple"></iconify-icon> <?php echo __('dashboard.media'); ?></button>
            <?php if (!empty($config['comments_enabled'])) { ?>
                <button class="pw-btn" onclick="window.location.href=window.PW_BASE_PATH+'/dashboard/comments'"><iconify-icon icon="mdi:comment-text-multiple-outline"></iconify-icon> <?php echo __('comments.title'); ?></button>
            <?php } ?>
            <?php if (hasRole('admin')) { ?>
                <button class="pw-btn" onclick="window.location.href=window.PW_BASE_PATH+'/dashboard/settings'"><iconify-icon icon="mdi:cog"></iconify-icon> <?php echo __('setup.wiki_settings'); ?></button>
            <?php } ?>
            <div class="pw-user-menu-wrapper" id="pw-user-menu-wrapper">
                <button class="pw-user-badge-btn" id="pw-user-menu-toggle" aria-label="User Menu" title="<?php echo htmlspecialchars($_SESSION['pw_user'] ?? ''); ?>">
                    <?php echo htmlspecialchars(getCurrentUserInitials()); ?>
                </button>
                <div class="pw-user-dropdown" id="pw-user-dropdown">
                    <div class="pw-user-dropdown-header">
                        <span class="pw-user-dropdown-name"><?php echo htmlspecialchars($_SESSION['pw_user'] ?? ''); ?></span>
                        <span class="pw-user-dropdown-role"><?php echo htmlspecialchars(ucfirst($_SESSION['pw_role'] ?? '')); ?></span>
                    </div>
                    <div class="pw-user-dropdown-divider"></div>
                    <button class="pw-user-dropdown-item" id="pw-btn-change-password">
                        <iconify-icon icon="mdi:key-outline"></iconify-icon> <?php echo __('auth.change_password'); ?>
                    </button>
                    <div class="pw-user-dropdown-divider"></div>
                    <button class="pw-user-dropdown-item pw-danger" onclick="logoutAndRedirect()">
                        <iconify-icon icon="mdi:logout"></iconify-icon> <?php echo __('dashboard.logout'); ?>
                    </button>
                </div>
            </div>
        </div>
    </header>

    <div class="pw-dashboard-container">
        <!-- Sidebar -->
        <aside class="pw-dashboard-sidebar">
            <div class="pw-tree-search">
                <iconify-icon icon="mdi:magnify" class="pw-tree-search-icon"></iconify-icon>
                <input type="text" id="pw-tree-search-input" class="pw-input" placeholder="<?php echo __('dashboard.search_pages'); ?>">
            </div>
            <div class="pw-treeview">
                <ul class="pw-treeview-pages">
                    <li class="pw-tree-node pw-has-children pw-expanded">
                        <div class="pw-tree-item pw-tree-active" data-path="/">
                            <span class="pw-tree-toggle"></span>
                            <span class="pw-tree-label"><?php echo __('dashboard.startpage'); ?></span>
                        </div>
                        <?php echo buildAdminTree($tree); ?>
                    </li>
                </ul>
                <hr style="border: 0; border-top: 1px solid var(--pw-border); margin: 15px 0;">
                <!-- Virtual System Pages -->
                <div style="padding: 0 10px; margin-bottom: 8px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: var(--pw-text-muted);">
                    <?php echo __('dashboard.virtual_system_pages'); ?>
                </div>
                <ul style="margin-top: 10px; max-height: 25%; min-height: 20%; overflow-y: auto;">
                    <li class="pw-tree-node">
                        <div class="pw-tree-item" data-path="/_virtual/left_sidebar">
                            <span class="pw-tree-label"><iconify-icon icon="mdi:page-layout-sidebar-left" style="margin-right: 5px;"></iconify-icon> <?php echo __('dashboard.left_sidebar'); ?></span>
                        </div>
                    </li>
                    <li class="pw-tree-node">
                        <div class="pw-tree-item" data-path="/_virtual/right_sidebar">
                            <span class="pw-tree-label"><iconify-icon icon="mdi:page-layout-sidebar-right" style="margin-right: 5px;"></iconify-icon> <?php echo __('dashboard.right_sidebar'); ?></span>
                        </div>
                    </li>
                    <li class="pw-tree-node">
                        <div class="pw-tree-item" data-path="/_virtual/mobile_sidebar">
                            <span class="pw-tree-label"><iconify-icon icon="mdi:cellphone" style="margin-right: 5px;"></iconify-icon> <?php echo __('dashboard.mobile_sidebar'); ?></span>
                        </div>
                    </li>
                    <li class="pw-tree-node">
                        <div class="pw-tree-item" data-path="/_virtual/footer">
                            <span class="pw-tree-label"><iconify-icon icon="mdi:page-layout-footer" style="margin-right: 5px;"></iconify-icon> <?php echo __('dashboard.footer'); ?></span>
                        </div>
                    </li>
                    <li class="pw-tree-node">
                        <div class="pw-tree-item" data-path="/_virtual/404">
                            <span class="pw-tree-label"><iconify-icon icon="mdi:file-document-error" style="margin-right: 5px;"></iconify-icon> <?php echo __('dashboard.not_found_page'); ?></span>
                        </div>
                    </li>
                </ul>
                <hr style="border: 0; border-top: 1px solid var(--pw-border); margin: 15px 0;">
                <!-- Snippets -->
                <div style="padding: 0 10px; margin-bottom: 8px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: var(--pw-text-muted); display: flex; align-items: center; justify-content: space-between;">
                    <?php echo __('dashboard.snippets') ?? 'Snippets'; ?>
                    <button id="pw-btn-add-snippet" class="pw-btn" style="padding: 2px 5px; min-height: unset; font-size: 1.1em;" title="<?php echo __('dashboard.add_snippet') ?? 'Add Snippet'; ?>" aria-label="<?php echo __('dashboard.add_snippet') ?? 'Add Snippet'; ?>"><iconify-icon icon="mdi:plus"></iconify-icon></button>
                </div>
                <ul id="pw-snippets-list" style="margin-top: 10px; max-height: 25%; min-height: 20%; overflow-y: auto;">
                    <?php
                    $snippetsDir = realpath(__DIR__ . '/../../pages/_snippets');
                    $snippets = getSnippetsList($snippetsDir);
                    foreach ($snippets as $snippet) {
                        echo '<li class="pw-tree-node">';
                        echo '<div class="pw-tree-item" data-path="' . htmlspecialchars($snippet['path']) . '">';
                        echo '<span class="pw-tree-label"><iconify-icon icon="mdi:code-tags" style="margin-right: 5px;"></iconify-icon> ' . htmlspecialchars($snippet['name']) . '</span>';
                        echo '</div></li>';
                    }
                    ?>
                </ul>
            </div>
            <?php if (hasRole('admin')) { ?>
            <div class="pw-sidebar-bottom">
                <button class="pw-btn pw-sidebar-trash-btn" onclick="window.location.href = window.PW_BASE_PATH + '/dashboard/trash'" title="<?php echo __('dashboard.trash_title'); ?>" aria-label="<?php echo __('dashboard.trash_title'); ?>">
                    <iconify-icon icon="mdi:delete-clock-outline"></iconify-icon> <?php echo __('dashboard.trash'); ?>
                </button>
            </div>
            <?php } ?>
        </aside>

        <!-- Main Content -->
        <main class="pw-dashboard-main">
            <div id="pw-main-actions" class="pw-content-actions" style="display: none;">
                <button id="pw-btn-add-subpage" class="pw-btn pw-btn-primary"><iconify-icon icon="mdi:file-document-plus"></iconify-icon> <?php echo __('dashboard.add_subpage'); ?></button>
                <button id="pw-btn-edit-page" class="pw-btn"><iconify-icon icon="mdi:pencil"></iconify-icon> <?php echo __('dashboard.edit_page'); ?></button>
                <button id="pw-btn-page-settings-dash" class="pw-btn"><iconify-icon icon="mdi:cog"></iconify-icon> <?php echo __('editor.page_settings'); ?></button>
                <button id="pw-btn-duplicate-page" class="pw-btn"><iconify-icon icon="mdi:content-copy"></iconify-icon> <?php echo __('dashboard.duplicate_page'); ?></button>
                <button id="pw-btn-delete-page" class="pw-btn pw-btn-danger"><iconify-icon icon="mdi:delete"></iconify-icon> <?php echo __('dashboard.delete_page'); ?></button>
            </div>

            <div id="pw-main-content" class="pw-content-area">
                <h2><?php echo __('dashboard.select_action'); ?></h2>
                <p><?php echo __('dashboard.select_page_hint'); ?></p>
            </div>
        </main>
    </div>

    <!-- Core Scripts -->
    <script>
        // Find all available layouts in the current theme
        window.pwAvailableLayouts = <?php
            $activeTheme = $config['current_theme'] ?? 'default';
            $themesDir = realpath(__DIR__ . '/../../themes/' . $activeTheme);
            $themeFiles = $themesDir ? glob($themesDir . '/*.php') : [];
            $layouts = [];
            foreach ($themeFiles as $file) {
                $slug = pathinfo($file, PATHINFO_FILENAME);
                $label = prepareTitle($slug);
                $layouts[] = ['slug' => $slug, 'label' => $label];
            }
            echo json_encode($layouts);
        ?>;
    </script>
    <?php echo getLanguageScript(); ?>
    <script src="<?php echo BASE_PATH; ?>/purewiki/assets/js/i18n.js"></script>
    <script src="<?php echo BASE_PATH; ?>/purewiki/assets/js/core.js"></script>
    <script src="<?php echo BASE_PATH; ?>/purewiki/assets/js/page-picker.js"></script>
    <script>window.PW_DEBUG = <?php echo !empty($config['dev_debug_output']) ? 'true' : 'false'; ?>;</script>
    <script src="<?php echo BASE_PATH; ?>/purewiki/assets/js/editor.js"></script>
    <script src="<?php echo BASE_PATH; ?>/purewiki/assets/js/notify.js"></script>
    <script src="<?php echo BASE_PATH; ?>/purewiki/assets/js/clear_cache.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            initDialogSystem();
            initUserMenu();
            initTreeview();
            initSnippets();
            initDashboardInteractions();
            initTreeDragAndDrop();
            initTreeSearch();
            initAdminSidebarToggle('pw-dashboard-sidebar-toggle');

            const savedPath = sessionStorage.getItem('pw-active-page-path');
            if (savedPath) {
                const item = document.querySelector(`.pw-tree-item[data-path="${savedPath}"]`);
                if (item) {
                    item.click();
                }
            }
        });


        async function logoutAndRedirect() {
            await apiCall('logout');
            window.location.href = window.PW_BASE_PATH + '/dashboard/login';
        }
    </script>

    <!-- Templates -->
    <template id="tpl-page-header">
        <h2 data-field="label"></h2>
        <p><?php echo __('dashboard.path_label'); ?> <code data-field="path"></code></p>
    </template>

    <template id="tpl-page-info">
        <div class="pw-card pw-page-meta">
            <h3><?php echo __('dashboard.page_info'); ?> 
                <span class="pw-draft-badge" data-field="draft-badge" style="display:none; font-size:10px; margin-left:10px;"><?php echo __('dashboard.draft'); ?></span>
                <span class="pw-private-badge" data-field="private-badge" style="display:none; font-size:10px; margin-left:10px;"><?php echo __('dashboard.private'); ?></span>
            </h3>
            <p><strong><?php echo __('dashboard.author'); ?>:</strong> <span data-field="author"></span></p>
            <p><strong><?php echo __('dashboard.created'); ?>:</strong> <span data-field="created"></span></p>
            <p><strong><?php echo __('dashboard.modified'); ?>:</strong> <span data-field="modified"></span></p>
            <p><strong><?php echo __('dashboard.description'); ?>:</strong> <span data-field="description"></span></p>
            <p><strong><?php echo __('editor.tags_label'); ?>:</strong> <span data-field="tags"></span></p>
            <p><strong><?php echo __('dashboard.translations'); ?>:</strong> <span data-field="translations"></span></p>
        </div>
    </template>

    <template id="tpl-rename-card">
        <div class="pw-card">
            <h3><?php echo __('dashboard.url_folder_name'); ?></h3>
            <div class="pw-form-row pw-w-xlg">
                <span class="pw-muted-mono" data-field="parent-path"></span>
                <input type="text" id="pw-input-rename-folder" class="pw-input" autocomplete="off" style="flex:1;" data-field="current-name">
                <button id="pw-btn-rename-folder" class="pw-btn pw-btn-primary"><?php echo __('common.save'); ?></button>
            </div>
            <p class="pw-hint-small"><?php echo __('dashboard.rename_warning'); ?></p>
        </div>
    </template>

    <template id="tpl-move-card">
        <div class="pw-card">
            <h3><?php echo __('dashboard.move_page'); ?></h3>
            <div class="pw-form-row">
                <button id="pw-btn-move-up" class="pw-btn" data-dir="up"><iconify-icon icon="mdi:arrow-up"></iconify-icon> <?php echo __('dashboard.move_up'); ?></button>
                <button id="pw-btn-move-down" class="pw-btn" data-dir="down"><iconify-icon icon="mdi:arrow-down"></iconify-icon> <?php echo __('dashboard.move_down'); ?></button>
            </div>
            <p class="pw-hint-small"><?php echo __('dashboard.move_hint'); ?></p>
        </div>
    </template>

    <template id="tpl-redirect-card">
        <div class="pw-card">
            <h3><?php echo __('dashboard.redirect_link'); ?></h3>
            <div class="pw-form-row pw-w-xlg">
                <label class="pw-toggle-label">
                    <input type="checkbox" id="pw-check-enable-redirect" class="pw-checkbox"> <?php echo __('dashboard.enable_redirect'); ?>
                </label>
            </div>
            <div class="pw-form-row pw-w-xlg">
                <input type="text" class="pw-input" id="pw-input-redirect-url" autocomplete="off" placeholder="<?php echo __('dashboard.redirect_placeholder'); ?>" style="flex:1;">
                <button id="pw-btn-save-redirect" class="pw-btn pw-btn-primary"><?php echo __('common.save'); ?></button>
            </div>
            <p class="pw-hint-small"><?php echo __('dashboard.redirect_hint'); ?></p>
        </div>
    </template>

    <template id="tpl-comments-card">
        <div class="pw-card pw-comments-card">
            <h3>
                <iconify-icon icon="mdi:comment-text-multiple-outline" class="pw-icon-left"></iconify-icon> 
                <?php echo __('comments.title'); ?>
                <span class="pw-badge pw-badge-warning" data-field="pending-count" style="display: none; font-size: 0.8rem; margin-left: 8px;"></span>
            </h3>
            <div data-field="comments-list" class="pw-comments-admin-list">
            </div>
            <p data-field="no-comments" class="pw-text-muted" style="display: none; margin: 10px 0;"><?php echo __('comments.no_comments'); ?></p>
        </div>
    </template>

    <template id="tpl-recent-pages-card">
        <div class="pw-card pw-recent-pages-card">
            <h3>
                <iconify-icon icon="mdi:history" class="pw-icon-left"></iconify-icon>
                <?php echo __('dashboard.recent_pages_title'); ?>
            </h3>
            <div data-field="recent-pages-list" class="pw-recent-pages-list">
            </div>
            <p data-field="no-recent-pages" class="pw-text-muted" style="display: none; margin: 10px 0;"><?php echo __('dashboard.no_recent_pages'); ?></p>
        </div>
    </template>

</body>
</html>
