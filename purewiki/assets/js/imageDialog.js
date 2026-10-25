/**
 * PureWiki - Image Selection Dialog
 *
 * Media dialog to pick or upload images and videos.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

/**
 * Initializes and opens the custom Image Selection Dialog
 * @param {Object} toolInstance active tool, receives the url via onMediaSelected() or onImageSelected()
 * @param {Object} options mediaType 'image' or 'video'
 */
function openImageSelectionDialog(toolInstance, options = {}) {
    const mediaType = options.mediaType === 'video' ? 'video' : 'image';
    const isVideo = mediaType === 'video';
    const selectMedia = (url) => typeof toolInstance.onMediaSelected === 'function'
        ? toolInstance.onMediaSelected(url)
        : toolInstance.onImageSelected(url);
    const overlay = document.getElementById('pw-image-dialog-overlay');
    const titleEl = document.getElementById('pw-image-dialog-title');
    const cancelBtn = document.getElementById('pw-image-dialog-cancel');
    const scopeSelect = document.getElementById('pw-image-scope');
    const dropZone = document.getElementById('pw-image-upload-zone');
    const fileInput = document.getElementById('pw-image-upload-input');
    const grid = document.getElementById('pw-image-grid');
    const manualPathInput = document.getElementById('pw-image-manual-path');
    const manualPathBtn = document.getElementById('pw-image-btn-manual');

    if (!overlay) {
        notify('Image dialog UI not found', 'error');
        return;
    }

    // Extract current page from URL
    const urlParams = new URLSearchParams(window.location.search);
    const currentPagePath = urlParams.get('path') || '/';

    async function loadImages() {
        const scope = scopeSelect.value;
        const targetPath = scope === 'current' ? currentPagePath : '__global__';
        grid.innerHTML = '<p style="padding:10px; color:var(--pw-text-muted);">Loading...</p>';

        const formData = new FormData();
        formData.append('action', 'list_media');
        formData.append('path', targetPath);

        // get csrf token from meta tag in head
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const headers = {};
        if (csrfToken) {
            headers['X-CSRF-Token'] = csrfToken;
        }

        try {
            const res = await fetch((window.PW_BASE_PATH || '') + '/purewiki/api.php', { 
                method: 'POST', 
                headers: headers,
                body: formData 
            });
            const result = await res.json();

            grid.innerHTML = '';
            if (result.success && result.data.length > 0) {
                const images = result.data.filter(f => f.type === mediaType);

                if (images.length === 0) {
                    grid.innerHTML = '<p style="padding:10px; color:var(--pw-text-muted);">' + (isVideo ? __('editor.no_videos_found') : 'No images found here.') + '</p>';
                    return;
                }

                images.forEach(f => {
                    const card = document.createElement('div');
                    card.style.cssText = 'border: 1px solid var(--pw-border); border-radius: 4px; overflow: hidden; cursor: pointer; transition: transform 0.2s; position: relative; background: var(--pw-bg-panel); display: flex; flex-direction: column;';

                    const imgContainer = document.createElement('div');
                    imgContainer.style.cssText = 'width: 100%; height: 100px; display: flex; align-items: center; justify-content: center; background: #000;';

                    const img = document.createElement(isVideo ? 'video' : 'img');
                    const basePath = targetPath === '__global__' ? '/pages/' : '/pages/' + (targetPath === '/' ? '' : targetPath) + '/';
                    const imgUrl = (basePath + f.name).replace(/\/\/+/g, '/');

                    if (isVideo) {
                        img.muted = true;
                        img.preload = 'metadata';
                    }
                    img.src = (window.PW_BASE_PATH || '') + imgUrl;
                    img.style.cssText = 'max-width: 100%; max-height: 100%; object-fit: contain;';

                    imgContainer.appendChild(img);

                    const label = document.createElement('div');
                    label.style.cssText = 'padding: 5px; font-size: 11px; text-overflow: ellipsis; white-space: nowrap; overflow: hidden; text-align: center; color: var(--pw-text-muted); border-top: 1px solid var(--pw-border);';
                    label.textContent = f.name;

                    card.appendChild(imgContainer);
                    card.appendChild(label);

                    card.addEventListener('mouseenter', () => card.style.transform = 'scale(1.03)');
                    card.addEventListener('mouseleave', () => card.style.transform = 'scale(1)');

                    card.addEventListener('click', () => {
                        selectMedia(imgUrl);
                        closeModal();
                    });

                    grid.appendChild(card);
                });
            } else {
                grid.innerHTML = '<p style="padding:10px; color:var(--pw-text-muted);">No images found.</p>';
            }
        } catch (e) {
            grid.innerHTML = '<p style="padding:10px; color:var(--pw-text-muted);">Failed to load media.</p>';
        }
    }

    async function handleUpload(filesList, overwrite = false) {
        const files = Array.from(filesList);
        if (!files || files.length === 0) return;
        
        if (!overwrite && typeof fileInput !== 'undefined' && fileInput) {
            fileInput.value = '';
        }

        const scope = scopeSelect.value;
        const targetPath = scope === 'current' ? currentPagePath : '__global__';

        const formData = new FormData();
        formData.append('action', 'upload_media');
        formData.append('path', targetPath);
        if (overwrite) {
            formData.append('overwrite', 'true');
        }
        for (let i = 0; i < files.length; i++) {
            formData.append('files[]', files[i]);
        }

        notify(`Uploading ${files.length} file(s)...`, 'info');

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const headers = {};
        if (csrfToken) {
            headers['X-CSRF-Token'] = csrfToken;
        }

        try {
            const res = await fetch((window.PW_BASE_PATH || '') + '/purewiki/api.php', { 
                method: 'POST', 
                headers: headers,
                body: formData 
            });
            const result = await res.json();

            if (result.require_confirmation) {
                const confirmMsg = __('media.file_exists_confirm', result.existing_files.join(', '));
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
            } else if (result.success) {
                notify(result.message, 'success');
                loadImages();
            } else {
                notify(result.message, 'error');
            }
        } catch (e) {
            notify('Upload failed due to network error.', 'error');
        }
    }

    scopeSelect.onchange = () => {
        loadImages();
    };
    function closeModal() {
        overlay.classList.remove('pw-show');
        // Clean up events
        cancelBtn.onclick = null;
        dropZone.onclick = null;
        dropZone.ondragover = null;
        dropZone.ondragleave = null;
        dropZone.ondrop = null;
        fileInput.onchange = null;
        scopeSelect.onchange = null;
        if (manualPathBtn) manualPathBtn.onclick = null;
        if (manualPathInput) manualPathInput.onkeydown = null;
    }

    cancelBtn.onclick = () => closeModal();

    // Manual Path Add
    if (manualPathBtn && manualPathInput) {
        manualPathInput.value = '';

        const applyManualPath = () => {
            const val = manualPathInput.value.trim();
            if (val) {
                selectMedia(val);
                closeModal();
            }
        };

        manualPathBtn.onclick = applyManualPath;
        manualPathInput.onkeydown = (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                applyManualPath();
            }
        };
    }

    dropZone.onclick = () => fileInput.click();
    fileInput.onchange = (e) => handleUpload(e.target.files);

    dropZone.ondragover = (e) => { e.preventDefault(); dropZone.style.background = 'var(--pw-border)'; };
    dropZone.ondragleave = (e) => { e.preventDefault(); dropZone.style.background = 'transparent'; };
    dropZone.ondrop = (e) => {
        e.preventDefault();
        dropZone.style.background = 'transparent';
        if (e.dataTransfer && e.dataTransfer.files) {
            handleUpload(e.dataTransfer.files);
        }
    };

    if (titleEl) titleEl.textContent = __(isVideo ? 'editor.select_video' : 'editor.select_image');
    fileInput.accept = isVideo ? 'video/*' : '';

    overlay.classList.add('pw-show');
    scopeSelect.value = 'current';
    loadImages();
}
