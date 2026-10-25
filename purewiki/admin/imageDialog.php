<?php
/**
 * PureWiki - Image Selection Dialog
 *
 * Media dialog, opened with openImageSelectionDialog() from imageDialog.js.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');
?>
<div id="pw-image-dialog-overlay" class="pw-dialog-overlay pw-image-dialog">
    <div class="pw-dialog-box">
        <h3 id="pw-image-dialog-title" class="pw-dialog-title"><?php echo __('editor.select_image'); ?></h3>

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
