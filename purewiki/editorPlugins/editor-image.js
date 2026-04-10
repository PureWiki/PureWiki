/**
 * PureWiki - Image Tool
 *
 * Image upload and management tool for Editor.js using PureWiki Media Manager integration.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

class ExtendedImage {
    static get toolbox() {
        return {
            title: typeof __ === 'function' ? __('plugins.image') : 'Image',
            icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/></svg>'
        };
    }

    constructor({ data, api, config, readOnly }) {
        this.api = api;
        this.data = {
            url: data.url || '',
            caption: data.caption || '',
            showCaption: data.showCaption !== undefined ? data.showCaption : true
        };
        this.readOnly = readOnly;
        this.wrapper = undefined;
    }

    render() {
        this.wrapper = document.createElement('div');
        this.wrapper.classList.add('pw-extended-image-wrapper');

        if (this.data.url) {
            this._createImageView();
        } else {
            this._createSelectView();
        }

        return this.wrapper;
    }

    save(blockContent) {
        const imageEl = blockContent.querySelector('img');
        const captionEl = blockContent.querySelector('.pw-image-caption');

        let savedUrl = this.data.url;
        if (imageEl) {
            let rawSrc = imageEl.getAttribute('src');
            const basePath = window.PW_BASE_PATH || '';
            // If the browser returned an absolute URL, strip the origin
            if (rawSrc && rawSrc.startsWith(window.location.origin)) {
                rawSrc = rawSrc.replace(window.location.origin, '');
            }
            if (basePath && rawSrc && rawSrc.startsWith(basePath + '/')) {
                savedUrl = rawSrc.substring(basePath.length);
            } else if (rawSrc) {
                savedUrl = rawSrc;
            }
        }

        return {
            url: savedUrl,
            caption: captionEl ? captionEl.textContent : this.data.caption,
            showCaption: this.data.showCaption
        };
    }

    validate(savedData) {
        return savedData.url.trim() !== '';
    }

    _createSelectView() {
        this.wrapper.innerHTML = '';
        const container = document.createElement('div');
        container.style.cssText = 'padding: 40px; text-align: center; border: 2px dashed var(--pw-border); border-radius: 4px; background: var(--pw-bg-panel); color: var(--pw-text-muted);';

        const button = document.createElement('button');
        button.className = 'pw-btn pw-btn-primary';
        button.type = 'button';
        button.innerHTML = '<iconify-icon icon="mdi:image"></iconify-icon> ' + (typeof __ === 'function' ? __('plugins.image_select') : 'Select Image');

        button.addEventListener('click', () => {
            if (typeof openImageSelectionDialog === 'function') {
                openImageSelectionDialog(this);
            } else {
                notify(typeof __ === 'function' ? __('plugins.image_not_available') : 'Image selection dialog not available.', 'error');
            }
        });

        container.appendChild(button);
        this.wrapper.appendChild(container);
    }

    _createImageView() {
        this.wrapper.innerHTML = '';

        const img = document.createElement('img');
        const basePath = window.PW_BASE_PATH || '';
        let displayUrl = this.data.url;
        if (displayUrl && displayUrl.startsWith('/') && !displayUrl.startsWith('//')) {
            displayUrl = basePath + displayUrl;
        }
        img.src = displayUrl;
        img.style.cssText = 'max-width: 100%; display: block; border-radius: 4px;';

        const bottomRow = document.createElement('div');
        bottomRow.style.cssText = 'display: flex; justify-content: space-between; align-items: flex-start; gap: 10px; margin-top: 5px;';

        const caption = document.createElement('div');
        caption.className = 'pw-image-caption';
        caption.contentEditable = !this.readOnly;
        caption.dataset.placeholder = typeof __ === 'function' ? __('plugins.image_caption_placeholder') : 'Enter a caption (Alt)...';
        caption.textContent = this.data.caption;
        caption.style.cssText = 'flex: 1; padding: 5px; text-align: left; color: var(--pw-text-muted); font-size: 0.9em; outline: none; min-height: 20px; border-bottom: 1px dashed transparent; transition: border-color 0.2s;';

        caption.addEventListener('focus', () => caption.style.borderBottom = '1px dashed var(--pw-border)');
        caption.addEventListener('blur', () => caption.style.borderBottom = '1px dashed transparent');

        const controlsWrap = document.createElement('div');
        controlsWrap.style.cssText = 'display: flex; align-items: center; gap: 6px; font-size: 0.85em; color: var(--pw-text-muted); flex-shrink: 0; padding: 5px; user-select: none;';

        const checkboxId = 'pw-img-caption-show-' + Math.random().toString(36).substr(2, 9);
        const checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.id = checkboxId;
        checkbox.checked = this.data.showCaption;
        checkbox.style.cssText = 'cursor: pointer; width: 14px; height: 14px; margin: 0; accent-color: var(--pw-primary);';

        checkbox.addEventListener('change', (e) => {
            this.data.showCaption = e.target.checked;
            if (window.pwEditorInitializing) return;
            if (typeof hasUnsavedChanges !== 'undefined') hasUnsavedChanges = true;
            if (typeof autoSaveTimeout !== 'undefined') clearTimeout(autoSaveTimeout);
            if (typeof saveCurrentDraft === 'function') {
                autoSaveTimeout = setTimeout(() => saveCurrentDraft(true), 1000);
            }
        });

        const checkLabel = document.createElement('label');
        checkLabel.htmlFor = checkboxId;
        checkLabel.textContent = typeof __ === 'function' ? __('plugins.show_caption') : 'Show Caption';
        checkLabel.style.cssText = 'cursor: pointer; padding-top: 1px;';

        if (!this.readOnly) {
            controlsWrap.appendChild(checkbox);
            controlsWrap.appendChild(checkLabel);
        }

        bottomRow.appendChild(caption);
        if (!this.readOnly) {
            bottomRow.appendChild(controlsWrap);
        }

        const wrap = document.createElement('div');
        wrap.style.position = 'relative';

        if (!this.readOnly) {
            const replaceBtn = document.createElement('button');
            replaceBtn.className = 'pw-btn';
            replaceBtn.innerHTML = '<iconify-icon icon="mdi:close"></iconify-icon>';
            replaceBtn.style.cssText = 'position: absolute; top: 10px; right: 10px; background: rgba(0,0,0,0.5); color: white; border: none; padding: 5px; border-radius: 4px; cursor: pointer; display: flex; align-items: center; line-height: 1;';
            replaceBtn.addEventListener('click', () => {
                this.data.url = '';
                this.data.caption = '';
                this._createSelectView();
            });
            wrap.appendChild(replaceBtn);
        }

        wrap.appendChild(img);
        this.wrapper.appendChild(wrap);
        this.wrapper.appendChild(bottomRow);
    }

    onImageSelected(url) {
        this.data.url = url;
        this._createImageView();
    }
}
