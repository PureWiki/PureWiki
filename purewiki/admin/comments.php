<?php
/**
 * PureWiki - Comments Management Page
 *
 * Dashboard view for global comment management.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

require_once __DIR__ . '/../core/i18n.php';
require_once __DIR__ . '/../core/asset_manager.php';
require_once __DIR__ . '/../core/config.php';

$pageTitle = __('comments.dashboard_title') . ' - PureWiki';
require_once __DIR__ . '/layout_head.php';
?>
<body class="pw-dashboard-body">

    <!-- Header -->
    <header class="pw-dashboard-header">
        <div class="pw-header-left">
            <h1 class="pw-site-title"><?php echo htmlspecialchars(__('comments.dashboard_title')); ?></h1>
        </div>
        <div class="pw-header-right">
            <button class="pw-btn" onclick="window.location.href = window.PW_BASE_PATH + '/dashboard'">
                <iconify-icon icon="mdi:arrow-left"></iconify-icon> <?php echo __('settings.back_to_dashboard'); ?>
            </button>
        </div>
    </header>

    <div class="pw-dashboard-container">
        <main class="pw-dashboard-main pw-comments-main">
            <!-- Filter Tabs -->
            <div class="pw-tab-nav" style="display: flex; gap: 15px; margin-bottom: 20px; border-bottom: 1px solid var(--pw-border); padding-bottom: 10px;">
                <button class="pw-btn pw-comment-tab-btn pw-btn-primary" data-filter="all"><?php echo __('comments.filter_all'); ?></button>
                <button class="pw-btn pw-comment-tab-btn" data-filter="pending"><?php echo __('comments.status_pending'); ?> <span id="pw-pending-badge" class="pw-badge pw-badge-warning" style="display: none; font-size: 0.8rem; margin-left: 5px;">0</span></button>
                <button class="pw-btn pw-comment-tab-btn" data-filter="approved"><?php echo __('comments.status_approved'); ?></button>
                <button class="pw-btn pw-comment-tab-btn" data-filter="hidden"><?php echo __('comments.status_hidden'); ?></button>
            </div>

            <!-- List Container -->
            <div class="pw-settings-panel">
                <div id="pw-comments-list-wrapper">
                    <p class="pw-hint"><?php echo __('common.loading'); ?></p>
                </div>
            </div>
        </main>
    </div>

    <?php echo getLanguageScript(); ?>
    <script src="<?php echo BASE_PATH; ?>/purewiki/assets/js/i18n.js"></script>
    <script src="<?php echo BASE_PATH; ?>/purewiki/assets/js/core.js"></script>
    <?php
    $config = getGlobalConfig();
    ?>
    <script>window.PW_DEBUG = <?php echo !empty($config['dev_debug_output']) ? 'true' : 'false'; ?>;</script>
    <script src="<?php echo BASE_PATH; ?>/purewiki/assets/js/notify.js"></script>
    <script src="<?php echo BASE_PATH; ?>/purewiki/assets/js/comments.js"></script>

    <!-- Templates -->
    <template id="tpl-comment-row">
        <div class="pw-card pw-comment-row-item" style="margin-bottom: 15px; border-left: 4px solid var(--pw-border);">
            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 8px;">
                <div>
                    <strong data-field="author"></strong> 
                    <span class="pw-text-muted" style="font-size: 0.85rem; margin-left: 8px;">
                        &lt;<span data-field="email"></span>&gt;
                    </span>
                    <div style="margin-top: 4px; font-size: 0.85rem;">
                        <?php echo __('comments.page_column'); ?>: <a href="#" data-field="page-link" style="font-weight: 600;"></a>
                    </div>
                </div>
                <div class="pw-text-right">
                    <span class="pw-text-muted" style="font-size: 0.85rem;" data-field="date"></span>
                    <div style="margin-top: 4px;">
                        <span class="pw-badge" data-field="status-badge"></span>
                    </div>
                </div>
            </div>
            <div style="white-space: pre-wrap; margin: 10px 0; padding: 10px; background: rgba(255,255,255,0.02); border-radius: 4px;" data-field="text"></div>
            <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 10px;" data-field="actions">
                <button class="pw-btn pw-btn-primary pw-btn-sm pw-comments-approve-btn" style="display: none;">
                    <iconify-icon icon="mdi:check"></iconify-icon> <?php echo __('comments.approve'); ?>
                </button>
                <button class="pw-btn pw-btn-sm pw-comments-hide-btn" style="display: none;">
                    <iconify-icon icon="mdi:eye-off"></iconify-icon> <?php echo __('comments.hide'); ?>
                </button>
                <button class="pw-btn pw-btn-danger pw-btn-sm pw-comments-delete-btn">
                    <iconify-icon icon="mdi:delete"></iconify-icon> <?php echo __('common.delete'); ?>
                </button>
            </div>
        </div>
    </template>

</body>
</html>
