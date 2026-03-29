<?php
/**
 * PureWiki - Media Manager View
 *
 * Interface for managing uploaded files and assets
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/tree.php';
require_once __DIR__ . '/../core/i18n.php';
require_once __DIR__ . '/../core/asset_manager.php';
require_once __DIR__ . '/../core/fs.php';
$config = getGlobalConfig();
$wikiName = $config['wiki_name'] ?? 'PureWiki';
$pageTitle = 'PureWiki - ' . __('media.title');
$requireCroppie = true;
require_once __DIR__ . '/layout_head.php';
?>
<body class="pw-dashboard-body">

    <!-- Header -->
    <header class="pw-dashboard-header">
        <div class="pw-header-left">
            <h1 class="pw-site-title"><?php echo __('media.title'); ?></h1>
        </div>
        <div class="pw-header-right">
            <?php
            $backUrl = $_GET['from'] ?? '/dashboard';
            if (!str_starts_with($backUrl, '/')) $backUrl = '/dashboard';
            ?>
            <button class="pw-btn" onclick="window.location.href='<?php echo htmlspecialchars($backUrl, ENT_QUOTES); ?>'"><iconify-icon icon="mdi:arrow-left"></iconify-icon> <?php echo __('common.back'); ?></button>
        </div>
    </header>

    <div class="pw-dashboard-container">
        <!-- Sidebar -->
        <aside class="pw-dashboard-sidebar">
            <div class="pw-treeview">
                <ul>
                    <li class="pw-tree-node pw-expanded">
                        <div class="pw-tree-item pw-tree-active" data-path="__global__">
                            <iconify-icon icon="mdi:earth" style="margin-right: 8px;"></iconify-icon>
                            <span class="pw-tree-label"><?php echo __('media.global_label'); ?></span>
                        </div>
                    </li>
                    <li class="pw-tree-node pw-has-children pw-expanded">
                        <div class="pw-tree-item" data-path="/">
                            <span class="pw-tree-toggle"></span>
                            <span class="pw-tree-label"><?php echo __('media.pages_label'); ?></span>
                        </div>
                        <?php 
                        $pagesDir = getPageDir();
                        $tree = getCachedPagesTree($pagesDir);
                        echo buildAdminTree($tree);
                        ?>
                    </li>
                </ul>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="pw-dashboard-main pw-dashboard-main-column">
            <!-- Upload Zone -->
            <div class="pw-media-upload-zone">
                <div id="pw-media-dropzone" class="pw-media-dropzone">
                    <iconify-icon icon="mdi:cloud-upload" class="pw-media-dropzone-icon"></iconify-icon>
                    <h3 class="pw-media-dropzone-title"><?php echo __('media.upload_hint'); ?></h3>
                    <p class="pw-media-dropzone-text"><?php echo __('media.max_filesize'); ?></p>
                    <input type="file" id="pw-media-file-input" multiple style="display: none;">
                </div>
            </div>

            <!-- Media Content Area -->
            <div id="pw-media-content" class="pw-content-area pw-media-content-scroll">
                <div class="pw-media-header-row">
                    <h2 id="pw-media-selection-title" class="pw-media-title"><?php echo __('media.global_media'); ?></h2>
                </div>

                <!-- Media Grid -->
                <div id="pw-media-grid" class="pw-media-grid">
                    <!-- Loading  -->
                    <div class="pw-media-empty-state">
                        <iconify-icon icon="mdi:loading" class="mdi-spin pw-media-icon-lg"></iconify-icon>
                        <p><?php echo __('media.loading_media'); ?></p>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Media Block Template (hidden) -->
    <template id="pw-media-block-template">
        <div class="pw-media-block">
            <div class="pw-media-preview">
                <!-- Image added dynamically -->
            </div>
            <div class="pw-media-info">
                <button class="pw-btn pw-btn-sm pw-media-menu-btn" aria-label="<?php echo __('media.media_options'); ?>" title="<?php echo __('media.media_options'); ?>">
                    <iconify-icon icon="mdi:dots-vertical"></iconify-icon>
                </button>
                <div class="pw-media-info-text">
                    <span class="pw-media-filename">filename</span>
                    <span class="pw-media-filesize">Filesize</span>
                </div>
            </div>
        </div>
    </template>

    <!-- Image Resize/Crop Dialog -->
    <div id="pw-crop-modal" class="pw-dialog-overlay">
        <div class="pw-dialog-box" style="max-width: 800px; width: 90%;">
            <h3 class="pw-dialog-title"><?php echo __('media.resize_crop_title'); ?></h3>
            <div id="pw-crop-container" style="width: 100%; height: 400px; background: #eee; margin-bottom: 20px;"></div>

            <div style="display: flex; gap: 15px; margin-bottom: 20px; align-items: center;">
                <label for="pw-crop-width"><?php echo __('media.width_px'); ?></label>
                <input type="number" id="pw-crop-width" class="pw-input" style="width: 100px;">
                <label for="pw-crop-height"><?php echo __('media.height_px'); ?></label>
                <input type="number" id="pw-crop-height" class="pw-input" style="width: 100px;">
                <button id="pw-btn-crop-reset" class="pw-btn pw-btn-sm" title="Reset to Original Dimensions" aria-label="Reset to Original Dimensions"><iconify-icon icon="mdi:refresh"></iconify-icon></button>
            </div>

            <div class="pw-dialog-actions" style="justify-content: space-between;">
                <div>
                    <button id="pw-btn-crop-restore" class="pw-btn pw-btn-secondary" style="display: none;"><iconify-icon icon="mdi:restore"></iconify-icon> <?php echo __('media.restore_original'); ?></button>
                </div>
                <div>
                    <button id="pw-btn-crop-cancel" class="pw-btn pw-btn-secondary"><?php echo __('common.cancel'); ?></button>
                    <button id="pw-btn-crop-apply" class="pw-btn pw-btn-primary"><iconify-icon icon="mdi:content-save"></iconify-icon> <?php echo __('media.apply_overwrite'); ?></button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <?php echo getLanguageScript(); ?>
    <script src="/purewiki/assets/js/i18n.js"></script>
    <script src="/purewiki/assets/js/core.js"></script>
    <script>window.PW_DEBUG = <?php echo !empty(getGlobalConfig()['dev_debug_output']) ? 'true' : 'false'; ?>;</script>
    <script src="/purewiki/assets/js/notify.js"></script>
    <?php echo AssetManager::getScripts('footer'); ?>
    <script src="/purewiki/assets/js/image_editor.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            initDialogSystem();

            let currentPath = '__global__';

            // Extract originating path to preselect sidebar
            const urlParams = new URLSearchParams(window.location.search);
            const fromParam = urlParams.get('from');
            if (fromParam && fromParam.includes('path=')) {
                try {
                    const fromUrlParams = new URLSearchParams(fromParam.split('?')[1]);
                    const p = fromUrlParams.get('path');
                    if (p) currentPath = p;
                } catch (e) {
                    notify(__('media.error_loading_media'), 'error');
                }
            }

            // Sidebar Selection
            const treeItems = document.querySelectorAll('.pw-tree-item');
            const selectionTitle = document.getElementById('pw-media-selection-title');

            treeItems.forEach(item => {
                item.addEventListener('click', (e) => {
                    if (e.target.classList.contains('pw-tree-toggle')) return;
                    treeItems.forEach(i => i.classList.remove('pw-tree-active'));
                    item.classList.add('pw-tree-active');
                    const label = item.querySelector('.pw-tree-label').textContent;
                    const path = item.getAttribute('data-path');
                    currentPath = path;
                    selectionTitle.textContent = (path === '__global__') ? __('media.global_media') : __('media.media_prefix') + (path === '/' ? __('media.startpage') : label);
                    loadMedia();
                });
            });

            // Toggle Sidebar Folders
            document.querySelectorAll('.pw-tree-toggle').forEach(toggle => {
                toggle.addEventListener('click', (e) => {
                    e.stopPropagation();
                    toggle.closest('.pw-tree-node').classList.toggle('pw-expanded');
                });
            });

            // Load Media
            async function loadMedia() {
                const grid = document.getElementById('pw-media-grid');
                grid.innerHTML = '<div class="pw-media-empty-state"><iconify-icon icon="mdi:loading" class="mdi-spin pw-media-icon-lg"></iconify-icon><p>' + __('common.loading') + '</p></div>';

                try {
                    const result = await apiCall('list_media', { path: currentPath });
                    if (result.success) renderMediaList(result.data);
                    else { notify(result.message, 'error'); renderMediaList([]); }
                } catch (e) { notify(__('media.error_loading_media'), 'error'); renderMediaList([]); }
            }

            // Upload
            const dropzone = document.getElementById('pw-media-dropzone');
            const fileInput = document.getElementById('pw-media-file-input');
            dropzone.onclick = () => fileInput.click();
            dropzone.ondragover = (e) => { e.preventDefault(); dropzone.classList.add('drag-over'); };
            dropzone.ondragleave = () => dropzone.classList.remove('drag-over');
            dropzone.ondrop = (e) => { e.preventDefault(); dropzone.classList.remove('drag-over'); handleUpload(e.dataTransfer.files); };
            fileInput.onchange = () => handleUpload(fileInput.files);

            async function handleUpload(filesList, overwrite = false) {
                const files = Array.from(filesList);
                if (!files.length) return;
                
                if (!overwrite && fileInput) {
                    fileInput.value = '';
                }

                const formData = new FormData();
                formData.append('action', 'upload_media');
                formData.append('path', currentPath);
                if (overwrite) {
                    formData.append('overwrite', 'true');
                }
                for (let f of files) formData.append('files[]', f);
                notify(__('editor.uploading_files', files.length), 'info');
                try {
                    const res = await (await fetch('/purewiki/api.php', { method: 'POST', body: formData })).json();

                    if (res.require_confirmation) {
                        const confirmMsg = __('media.file_exists_confirm', res.existing_files.join(', '));
                        const isConfirmed = await openDialog({
                            title: __('media.file_exists_title'),
                            text: confirmMsg,
                            type: 'confirm',
                            confirmText: __('common.confirm'),
                            cancelText: __('common.cancel')
                        });

                        if (isConfirmed) {
                            handleUpload(files, true);
                        }
                    } else if (res.success) {
                        notify(res.message, 'success');
                        loadMedia();
                    } else {
                        notify(res.message, 'error');
                    }
                } catch (e) { notify(__('media.upload_failed'), 'error'); }
            }

            // Delete
            async function deleteMedia(filename) {
                const isConfirmed = await openDialog({
                    title: __('media.delete_media'),
                    text: __('media.delete_media_confirm', filename),
                    type: 'confirm',
                    confirmText: __('common.delete'),
                    cancelText: __('common.cancel')
                });

                if (!isConfirmed) return;

                try {
                    const result = await apiCall('delete_media', { path: currentPath, filename: filename });
                    if (result.success) { notify(result.message, 'success'); loadMedia(); }
                    else notify(result.message, 'error');
                } catch (e) { notify(__('media.delete_failed'), 'error'); }
            }

            // Rename
            async function renameMedia(filename) {
                const result = await openDialog({
                    title: __('media.rename'),
                    text: __('media.rename_prompt'),
                    type: 'prompt',
                    defaultValue: filename,
                    confirmText: __('common.save'),
                    cancelText: __('common.cancel')
                });

                if (!result || !result.value) return;

                const newName = result.value.trim();
                if (!newName || newName === filename) return;

                try {
                    const result = await apiCall('rename_media', { path: currentPath, old_name: filename, new_name: newName });
                    if (result.success) { notify(result.message, 'success'); loadMedia(); }
                    else notify(result.message, 'error');
                } catch (e) { notify(__('media.rename_failed'), 'error'); }
            }

            // Render Media List as Grid
            function renderMediaList(files) {
                const grid = document.getElementById('pw-media-grid');
                grid.innerHTML = '';
                if (!files.length) {
                    grid.innerHTML = '<div class="pw-media-empty-state"><iconify-icon icon="mdi:folder-open" class="pw-media-icon-lg"></iconify-icon><p>' + __('media.no_files') + '</p></div>';
                    return;
                }
                const temp = document.getElementById('pw-media-block-template');
                files.forEach(file => {
                    const clone = temp.content.cloneNode(true);
                    const name = clone.querySelector('.pw-media-filename');
                    const size = clone.querySelector('.pw-media-filesize');
                    const preview = clone.querySelector('.pw-media-preview');
                    const menuBtn = clone.querySelector('.pw-media-menu-btn');
                    const block = clone.querySelector('.pw-media-block');

                    name.textContent = file.name;
                    size.textContent = file.size;
                    const publicPath = (currentPath === '__global__') ? '' : currentPath;
                    const url = (`/pages/${publicPath}/${file.name}`).replace(/\/\/+/g, '/');

                    if (file.type === 'image') {
                        preview.innerHTML = `<img src="${url}">`;

                        // Container for badges
                        const badgeContainer = document.createElement('div');
                        badgeContainer.className = 'pw-media-badges';
                        badgeContainer.style = 'position: absolute; top: 5px; right: 5px; display: flex; flex-direction: column; gap: 4px; z-index: 2;';

                        if (file.has_webp) {
                            const badgeWebp = document.createElement('div');
                            badgeWebp.className = 'pw-media-badge';
                            badgeWebp.innerHTML = '<iconify-icon icon="mdi:check-decagram" title="WebP Optimized"></iconify-icon> WebP';
                            badgeWebp.style = 'background: rgba(76, 175, 80, 0.9); color: white; padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: bold; display: flex; align-items: center; gap: 3px;';
                            badgeContainer.appendChild(badgeWebp);
                        }

                        if (file.has_backup) {
                            const badgeBackup = document.createElement('div');
                            badgeBackup.className = 'pw-media-badge';
                            badgeBackup.innerHTML = '<iconify-icon icon="mdi:restore" title="Original Backup Available"></iconify-icon> Edited';
                            badgeBackup.style = 'background: rgba(33, 150, 243, 0.9); color: white; padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: bold; display: flex; align-items: center; gap: 3px;';
                            badgeContainer.appendChild(badgeBackup);
                        }

                        if (file.has_webp || file.has_backup) {
                            preview.style.position = 'relative';
                            preview.appendChild(badgeContainer);
                        }
                    }
                    else preview.innerHTML = `<iconify-icon icon="${file.icon}" class="pw-media-icon-preview"></iconify-icon>`;

                    block.onclick = () => window.open(url, '_blank');
                    menuBtn.onclick = (e) => {
                        e.stopPropagation();
                        showContextMenu(e, url, file.name);
                    };
                    grid.appendChild(clone);
                });
            }

            function showContextMenu(e, url, filename) {
                document.querySelectorAll('.pw-media-context-menu').forEach(m => m.remove());
                const menu = document.createElement('div');
                menu.className = 'pw-media-context-menu';
                menu.style.left = e.clientX + 'px';
                menu.style.top = e.clientY + 'px';

                const addItem = (lbl, icon, cb, color) => {
                    const item = document.createElement('div');
                    item.className = 'pw-media-context-item';
                    if (color) item.style.color = color;
                    item.innerHTML = `<iconify-icon icon="${icon}"></iconify-icon> ${lbl}`;
                    item.onclick = (ev) => { ev.stopPropagation(); menu.remove(); cb(); };
                    menu.appendChild(item);
                };

                addItem(__('media.copy_path'), 'mdi:content-copy', () => {
                    navigator.clipboard.writeText(url).then(() => notify(__('media.copied'), 'info'));
                });

                addItem(__('media.copy_public_link'), 'mdi:link', () => {
                    const publicUrl = window.location.origin + url;
                    navigator.clipboard.writeText(publicUrl).then(() => notify(__('media.copied'), 'info'));
                });

                addItem(__('media.rename'), 'mdi:pencil-outline', () => renameMedia(filename));

                // Add Resize/Crop option to images only
                const ext = filename.split('.').pop().toLowerCase();
                if (['jpg', 'jpeg', 'png', 'webp'].includes(ext)) {
                    addItem(__('media.resize_crop'), 'mdi:crop-free', () => {
                        if (typeof openImageEditor === 'function') {
                            openImageEditor(url, currentPath, filename);
                        } else {
                            notify(__('media.image_editor_not_loaded'), 'error');
                        }
                    });
                }

                addItem(__('common.delete'), 'mdi:delete-outline', () => deleteMedia(filename), 'var(--pw-danger)');

                document.body.appendChild(menu);
                const close = () => { menu.remove(); document.removeEventListener('click', close); };
                setTimeout(() => document.addEventListener('click', close), 10);
            }

            // Preselect tree item if possible
            const targetItem = document.querySelector(`.pw-tree-item[data-path="${currentPath === '/' ? '\\/' : currentPath}"]`) || document.querySelector(`.pw-tree-item[data-path="__global__"]`);
            if (targetItem) {
                let parentNode = targetItem.closest('.pw-tree-node');
                while (parentNode) {
                    parentNode.classList.add('pw-expanded');
                    parentNode = parentNode.parentElement ? parentNode.parentElement.closest('.pw-tree-node') : null;
                }
                targetItem.click();
            } else {
                loadMedia();
            }
        });
    </script>
</body>
</html>
