<?php
/**
 * PureWiki - Editor View
 *
 * Content editor. Integrates Editor.js and page functions
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

$editPath = $_GET['path'] ?? '/';
$pageTitle = 'PureWiki - ' . __('editor.title');
$extraCss = [BASE_PATH . '/purewiki/assets/css/editor.css'];
require_once __DIR__ . '/layout_head.php';
?>
<body class="pw-dashboard-body">

    <!-- Editor Header -->
    <header class="pw-dashboard-header">
        <div class="pw-edit-header-left">
            <button id="pw-btn-back-to-dash" class="pw-btn" title="<?php echo __('editor.back_to_dashboard'); ?>" aria-label="<?php echo __('editor.back_to_dashboard'); ?>"><iconify-icon icon="mdi:arrow-left"></iconify-icon></button>
            <h2 id="pw-editor-title" class="pw-editor-title" title="Double click to edit" data-path="<?php echo htmlspecialchars($editPath); ?>"><?php echo __('common.loading'); ?></h2>
            <span id="pw-editor-draft-badge" class="pw-draft-badge" style="display: none;"><?php echo __('editor.draft_badge'); ?></span>
        </div>
        <div class="pw-edit-header-center">
            <div class="pw-history-dropdown" id="pw-history-dropdown">
                <button id="pw-btn-history" class="pw-btn" title="<?php echo __('editor.page_settings'); ?>" aria-label="<?php echo __('editor.page_settings'); ?>">
                    <iconify-icon icon="mdi:history"></iconify-icon>
                    <span id="pw-history-label">&ndash;</span>
                </button>
                <div id="pw-history-menu" class="pw-history-menu"></div>
            </div>
        </div>
        <div class="pw-edit-header-right">
            <button id="pw-btn-delete-draft" class="pw-btn pw-btn-danger" style="display: none;"><iconify-icon icon="mdi:delete-outline"></iconify-icon> <?php echo __('editor.delete_draft'); ?></button>
            <button id="pw-btn-preview" class="pw-btn"><iconify-icon icon="mdi:eye"></iconify-icon> <?php echo __('editor.preview'); ?></button>
            <button id="pw-btn-media" class="pw-btn" onclick="window.location.href=window.PW_BASE_PATH+'/dashboard/media?from='+encodeURIComponent(window.location.pathname+window.location.search)"><iconify-icon icon="mdi:image-multiple"></iconify-icon> <?php echo __('dashboard.media'); ?></button>
            <?php if (!str_starts_with($editPath, '/_virtual/') && !str_starts_with($editPath, '/_snippets/')): ?>
            <button id="pw-btn-page-settings" class="pw-btn"><iconify-icon icon="mdi:cog"></iconify-icon> <?php echo __('editor.page_settings'); ?></button>
            <?php endif; ?>
            <button id="pw-btn-publish" class="pw-btn pw-btn-primary"><iconify-icon icon="mdi:publish"></iconify-icon> <?php echo __('editor.publish'); ?></button>
        </div>
    </header>

    <div class="pw-dashboard-container">

        <!-- Edit Page Container -->
        <div id="pw-edit-container" class="pw-edit-container">
            <div class="pw-edit-content">
                <div id="pw-editorjs"></div>
            </div>
        </div>

    </div>

    <!-- Image Selection Dialog -->
    <div id="pw-image-dialog-overlay" class="pw-dialog-overlay pw-image-dialog">
        <div class="pw-dialog-box">
            <h3 class="pw-dialog-title"><?php echo __('editor.select_image'); ?></h3>

            <!-- Upload Zone -->
            <div id="pw-image-upload-zone" class="pw-upload-zone">
                <iconify-icon icon="mdi:cloud-upload"></iconify-icon>
                <p><?php echo __('editor.drag_drop_upload'); ?><br><small><?php echo __('editor.upload_save_hint'); ?></small></p>
                <input type="file" id="pw-image-upload-input" multiple style="display: none;">
            </div>

            <!-- Dropdown Filter -->
            <div class="pw-image-scope-row">
                <label for="pw-image-scope"><?php echo __('editor.location'); ?></label>
                <select id="pw-image-scope" class="pw-input">
                    <option value="current"><?php echo __('editor.current_page'); ?></option>
                    <option value="__global__"><?php echo __('editor.global_media'); ?></option>
                </select>
            </div>

            <!-- Image Grid -->
            <div id="pw-image-grid" class="pw-image-grid">
                <!-- Images loaded dynamically -->
            </div>

            <!-- Manual Path Entry -->
            <div class="pw-manual-path-row">
                <label for="pw-image-manual-path"><?php echo __('editor.manual_path_label'); ?></label>
                <input type="text" id="pw-image-manual-path" class="pw-input" placeholder="/pages/..." />
                <button id="pw-image-btn-manual" class="pw-btn pw-btn-primary"><?php echo __('common.add'); ?></button>
            </div>
            <div class="pw-dialog-actions">
                <button id="pw-image-dialog-cancel" class="pw-btn pw-btn-secondary"><?php echo __('common.cancel'); ?></button>
            </div>
        </div>
    </div>

    <!-- Editor.js Core Plugins -->  
    <script src="https://cdn.jsdelivr.net/npm/@editorjs/editorjs@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/@editorjs/paragraph@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/@editorjs/header@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/@editorjs/list@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/@editorjs/delimiter@latest"></script>
    <script src="<?php echo BASE_PATH; ?>/purewiki/editorPlugins/editor-raw.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@editorjs/table@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/@editorjs/inline-code@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/@editorjs/underline@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/@editorjs/marker@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/@editorjs/link-autocomplete@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/editorjs-drag-drop"></script>
    <!-- PureWiki Editor.js Plugins -->
    <script src="<?php echo BASE_PATH; ?>/purewiki/editorPlugins/editor-image.js"></script>
    <script src="<?php echo BASE_PATH; ?>/purewiki/editorPlugins/editor-markdown.js"></script>
    <script src="<?php echo BASE_PATH; ?>/purewiki/editorPlugins/editor-live-markdown.js"></script>
    <script src="<?php echo BASE_PATH; ?>/purewiki/editorPlugins/editor-pagelist.js"></script>
    <script src="<?php echo BASE_PATH; ?>/purewiki/editorPlugins/editor-pageinclude.js"></script>
    <script src="<?php echo BASE_PATH; ?>/purewiki/editorPlugins/editor-toc.js"></script>
    <script src="<?php echo BASE_PATH; ?>/purewiki/editorPlugins/editor-code-prism.js"></script>
    <script src="<?php echo BASE_PATH; ?>/purewiki/editorPlugins/editor-callout.js"></script>
    <script src="<?php echo BASE_PATH; ?>/purewiki/editorPlugins/editor-css-class-tune.js"></script>
    <script src="<?php echo BASE_PATH; ?>/purewiki/editorPlugins/editor-duplicate-tune.js"></script>
    <script src="<?php echo BASE_PATH; ?>/purewiki/editorPlugins/editor-text-align-tune.js"></script>
    <script src="<?php echo BASE_PATH; ?>/purewiki/editorPlugins/editor-hidden-tune.js"></script>
    <script src="<?php echo BASE_PATH; ?>/purewiki/editorPlugins/editor-accordion.js"></script>
    <script src="<?php echo BASE_PATH; ?>/purewiki/editorPlugins/editor-snippet.js"></script>
    <script src="<?php echo BASE_PATH; ?>/purewiki/editorPlugins/editor-grid.js"></script>
    <script src="<?php echo BASE_PATH; ?>/purewiki/editorPlugins/editor-block.js"></script>

    <!-- Core Scripts -->
    <?php echo getLanguageScript(); ?>
    <script src="<?php echo BASE_PATH; ?>/purewiki/assets/js/i18n.js"></script>
    <script src="<?php echo BASE_PATH; ?>/purewiki/assets/js/page-picker.js"></script>
    <script src="<?php echo BASE_PATH; ?>/purewiki/assets/js/core.js"></script>
    <script>window.PW_DEBUG = <?php echo !empty(getGlobalConfig()['dev_debug_output']) ? 'true' : 'false'; ?>;</script>
    <script src="<?php echo BASE_PATH; ?>/purewiki/assets/js/editor.js"></script>
    <script src="<?php echo BASE_PATH; ?>/purewiki/assets/js/notify.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', async () => {
            initDialogSystem();

            // Auto open the editor for the provided path
            const titleEl = document.getElementById('pw-editor-title');
            const path = titleEl ? titleEl.getAttribute('data-path') : null;

            if (path && typeof openEditor === 'function') {
                try {
                    // Promise that waits for Editor.js to be ready
                    await openEditor(path);


                } catch (err) {
                    if (window.PW_DEBUG) console.error('Editor init failed:', err);
                }
            }
        });
    </script>
</body>
</html>
