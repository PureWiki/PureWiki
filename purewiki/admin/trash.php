<?php
/**
 * PureWiki - Trash Manager
 *
 * Admin interface for viewing, restoring, and deleting trashed pages.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

require_once __DIR__ . '/../core/i18n.php';
require_once __DIR__ . '/../core/asset_manager.php';

$pageTitle = __('dashboard.trash_title') . ' - PureWiki';
require_once __DIR__ . '/layout_head.php';
?>
<body class="pw-dashboard-body">

    <!-- Header -->
    <header class="pw-dashboard-header">
        <div class="pw-header-left">
            <h1 class="pw-site-title"><?php echo htmlspecialchars(__('dashboard.trash_title')); ?></h1>
        </div>
        <div class="pw-header-right">
            <button class="pw-btn" onclick="window.location.href = window.PW_BASE_PATH + '/dashboard'">
                <iconify-icon icon="mdi:arrow-left"></iconify-icon> <?php echo __('settings.back_to_dashboard'); ?>
            </button>
            <button id="pw-btn-empty-trash" class="pw-btn pw-btn-danger">
                <iconify-icon icon="mdi:delete-sweep"></iconify-icon> <?php echo __('dashboard.trash_empty_btn'); ?>
            </button>
        </div>
    </header>

    <div class="pw-dashboard-container">
        <main class="pw-dashboard-main pw-trash-main">
            <div class="pw-settings-panel">
                <div id="pw-trash-list-wrapper">
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
    <script src="<?php echo BASE_PATH; ?>/purewiki/assets/js/trash.js"></script>

    <template id="tpl-trash-row">
        <tr>
            <td>
                <strong data-field="title"></strong>
                <div class="pw-muted-mono" style="font-size: 0.8em; margin-top: 2px;" data-field="original-slug"></div>
            </td>
            <td data-field="deleted-at"></td>
            <td data-field="children"></td>
            <td class="pw-text-right">
                <button class="pw-btn pw-btn-secondary pw-btn-sm pw-trash-restore-btn" title="<?php echo __('dashboard.trash_restore'); ?>" aria-label="<?php echo __('dashboard.trash_restore'); ?>">
                    <iconify-icon icon="mdi:restore"></iconify-icon> <?php echo __('dashboard.trash_restore'); ?>
                </button>
                <button class="pw-btn pw-btn-danger pw-btn-sm pw-trash-delete-btn" title="<?php echo __('dashboard.trash_delete_permanent'); ?>" aria-label="<?php echo __('dashboard.trash_delete_permanent'); ?>">
                    <iconify-icon icon="mdi:delete-forever"></iconify-icon> <?php echo __('dashboard.trash_delete_permanent'); ?>
                </button>
            </td>
        </tr>
    </template>

</body>
</html>
