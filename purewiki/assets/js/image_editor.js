/**
 * PureWiki - Image Editor
 * 
 * Logic for the Croppie-based image resizing and cropping tool.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

let croppieInst = null;
let currentImagePath = '';
let currentImageDir = '';
let currentImageFilename = '';
let originalWidth = 0;
let originalHeight = 0;

/**
 * Opens the Image Editor modal for a specific image.
 * @param {string} url The public URL of the image.
 * @param {string} dir The internal directory path.
 * @param {string} filename The filename of the image.
 */
async function openImageEditor(url, dir, filename) {
    currentImagePath = url;
    currentImageDir = dir;
    currentImageFilename = filename;

    const modal = document.getElementById('pw-crop-modal');
    if (!modal) return;

    // Check for existing backup to show Restore button
    await checkBackupStatus();
    modal.classList.add('pw-show');

    initCroppie(url);
}

/**
 * Initializes the Croppie instance.
 */
function initCroppie(url) {
    const container = document.getElementById('pw-crop-container');
    if (croppieInst) {
        croppieInst.destroy();
    }

    const defaultW = 400;
    const defaultH = 300;

    croppieInst = new Croppie(container, {
        viewport: { width: defaultW, height: defaultH },
        boundary: { width: '100%', height: '100%' },
        showZoomer: true,
        enableResize: true,
        enableOrientation: true
    });

    const img = new Image();
    img.onload = function() {
        originalWidth = this.naturalWidth;
        originalHeight = this.naturalHeight;

        const startW = Math.min(originalWidth, defaultW);
        const startH = Math.min(originalHeight, defaultH);

        document.getElementById('pw-crop-width').value = startW;
        document.getElementById('pw-crop-height').value = startH;

        croppieInst.bind({
            url: url
        }).then(() => {
            updateCroppieViewport();
        });
    };
    img.src = url;

    container.addEventListener('update', function(ev) {
    });
}

/** Updates the Croppie viewport size based on the input fields. */
function updateCroppieViewport() {
    if (!croppieInst) return;
    const w = parseInt(document.getElementById('pw-crop-width').value) || 100;
    const h = parseInt(document.getElementById('pw-crop-height').value) || 100;

    const container = document.getElementById('pw-crop-container');
    croppieInst.destroy();
    croppieInst = new Croppie(container, {
        viewport: { width: w, height: h },
        boundary: { width: '100%', height: '100%' },
        showZoomer: true,
        enableResize: true
    });
    croppieInst.bind({ url: currentImagePath });
}

/** Checks if a backup exists and toggles the Restore button. */
async function checkBackupStatus() {
    const btnRestore = document.getElementById('pw-btn-crop-restore');
    if (!btnRestore) return;

    btnRestore.style.display = 'inline-block'; 
}

/** Applies the crop */
async function applyImageEdit() {
    if (!croppieInst) return;

    const btnApply = document.getElementById('pw-btn-crop-apply');
    const ogHtml = btnApply.innerHTML;
    btnApply.disabled = true;
    btnApply.innerHTML = '<iconify-icon icon="line-md:loading-loop"></iconify-icon> Processing...';

    try {
        // 1. Ensure backup exists
        const backupRes = await apiCall('backup_image', { path: currentImageDir, filename: currentImageFilename });
        if (!backupRes.success && backupRes.message !== 'Backup already exists.') {
            notify(__('media.failed_create_backup'), 'error');
            btnApply.disabled = false;
            btnApply.innerHTML = ogHtml;
            return;
        }

        const size = {
            width: parseInt(document.getElementById('pw-crop-width').value) || 400,
            height: parseInt(document.getElementById('pw-crop-height').value) || 300
        };

        const resultBase64 = await croppieInst.result({
            type: 'base64',
            size: size,
            format: currentImageFilename.split('.').pop().toLowerCase() === 'png' ? 'png' : 'jpeg',
            quality: 0.9
        });

        const editRes = await apiCall('process_image_edit', {
            path: currentImageDir,
            filename: currentImageFilename,
            image_data: resultBase64
        });

        if (editRes.success) {
            notify(__('media.image_updated'), 'success');
            closeImageEditor();
            if (typeof loadMedia === 'function') loadMedia();
        } else {
            notify(editRes.message || __('media.failed_update_image'), 'error');
        }

    } catch (err) {
        console.error(err);
        notify(__('media.error_during_editing'), 'error');
    } finally {
        btnApply.disabled = false;
        btnApply.innerHTML = ogHtml;
    }
}

/** Restores the original image from backup. */
async function restoreOriginalImage() {
    const isConfirmed = await openDialog({
        title: __('media.restore_original'),
        text: __('editor.restore_version_confirm'),
        type: 'confirm'
    });
    if (!isConfirmed) return;

    try {
        const res = await apiCall('restore_image', { path: currentImageDir, filename: currentImageFilename });
        if (res.success) {
            notify(__('media.image_restored'), 'success');
            closeImageEditor();
            if (typeof loadMedia === 'function') loadMedia();
        } else {
            notify(res.message, 'error');
        }
    } catch (err) {
        notify(__('media.failed_restore_image'), 'error');
    }
}


function closeImageEditor() {
    const modal = document.getElementById('pw-crop-modal');
    if (modal) modal.classList.remove('pw-show');
    if (croppieInst) {
        croppieInst.destroy();
        croppieInst = null;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const btnCancel = document.getElementById('pw-btn-crop-cancel');
    const btnApply = document.getElementById('pw-btn-crop-apply');
    const btnRestore = document.getElementById('pw-btn-crop-restore');
    const btnReset = document.getElementById('pw-btn-crop-reset');

    const inputW = document.getElementById('pw-crop-width');
    const inputH = document.getElementById('pw-crop-height');

    if (btnCancel) btnCancel.addEventListener('click', closeImageEditor);
    if (btnApply) btnApply.addEventListener('click', applyImageEdit);
    if (btnRestore) btnRestore.addEventListener('click', restoreOriginalImage);

    if (btnReset) {
        btnReset.addEventListener('click', () => {
            if (inputW) inputW.value = originalWidth;
            if (inputH) inputH.value = originalHeight;
            updateCroppieViewport();
        });
    }

    /** Debounce manual input changes */
    let resizeTimer;
    const triggerResize = () => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(updateCroppieViewport, 500);
    };

    if (inputW) inputW.addEventListener('input', triggerResize);
    if (inputH) inputH.addEventListener('input', triggerResize);
});
