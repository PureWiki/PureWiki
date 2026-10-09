/**
 * PureWiki - Icon Tool
 *
 * Compact icon editor block and inline tool for Editor.js using Iconify.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

class IconTool {
    static get isReadOnlySupported() {
        return true;
    }

    static get toolbox() {
        return {
            title: typeof __ === 'function' ? __('plugins.icon') : 'Icon',
            icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M5.5 2C3.56 2 2 3.56 2 5.5v13C2 20.44 3.56 22 5.5 22H16l6-6V5.5C22 3.56 20.44 2 18.5 2zm.25 2h12.5A1.75 1.75 0 0 1 20 5.75V15h-1.5c-1.94 0-3.5 1.56-3.5 3.5V20H5.75A1.75 1.75 0 0 1 4 18.25V5.75A1.75 1.75 0 0 1 5.75 4m8.69 2.77c-.16 0-.32.02-.47.06c-.94.26-1.47 1.22-1.23 2.17c.05.15.12.3.21.44l3.23-.88q0-.255-.06-.51c-.21-.75-.9-1.28-1.68-1.28M8.17 8.5c-.17 0-.32 0-.47.05c-.93.26-1.48 1.22-1.23 2.15c.03.16.12.3.21.46l3.23-.88c0-.17-.02-.34-.06-.5A1.72 1.72 0 0 0 8.17 8.5m8.55 2.76l-9.13 2.51a5.27 5.27 0 0 0 5.36 1.64a5.27 5.27 0 0 0 3.77-4.15" /></svg>'
        };
    }

    constructor({ data, config, api, readOnly }) {
        this.api = api;
        this.readOnly = readOnly;
        this.data = {
            icon: data.icon !== undefined ? data.icon : 'mdi:emoticon',
            size: data.size || '',
            color: data.color || '',
            padding: data.padding || '',
            align: data.align || 'left'
        };

        this.nodes = {
            wrapper: null,
            previewIcon: null,
            previewBadge: null,
            inputIcon: null,
            inputSize: null,
            inputPadding: null,
            colorPicker: null,
            clearColorBtn: null,
            selectAlign: null
        };
    }

    /**  Update preview icon based on input value */
    _updatePreview() {
        if (!this.nodes.previewIcon) return;

        const iconName = (this.data.icon || '').trim();
        if (iconName) {
            this.nodes.previewIcon.setAttribute('icon', iconName);
            this.nodes.previewIcon.style.opacity = '1';
        } else {
            this.nodes.previewIcon.setAttribute('icon', 'mdi:help-circle-outline');
            this.nodes.previewIcon.style.opacity = '0.4';
        }

        const sizeVal = this.data.size ? (isNaN(this.data.size) ? this.data.size : `${this.data.size}px`) : '22px';
        this.nodes.previewIcon.style.fontSize = sizeVal;

        if (this.data.color) {
            this.nodes.previewIcon.style.color = this.data.color;
            if (this.nodes.clearColorBtn) this.nodes.clearColorBtn.style.display = 'inline-flex';
        } else {
            this.nodes.previewIcon.style.color = 'inherit';
            if (this.nodes.clearColorBtn) this.nodes.clearColorBtn.style.display = 'none';
        }

        const paddingVal = this.data.padding ? (isNaN(this.data.padding) ? this.data.padding : `${this.data.padding}px`) : '0px';
        this.nodes.previewIcon.style.padding = paddingVal;
    }

    render() {
        if (this.readOnly) {
            const container = document.createElement('div');
            container.classList.add('pw-icon-block', `pw-icon-block--${this.data.align || 'left'}`);
            if (this.data.align === 'center') container.style.justifyContent = 'center';
            if (this.data.align === 'right') container.style.justifyContent = 'flex-end';

            const iconEl = document.createElement('iconify-icon');
            iconEl.setAttribute('icon', this.data.icon || '');
            if (this.data.size) iconEl.style.fontSize = isNaN(this.data.size) ? this.data.size : `${this.data.size}px`;
            if (this.data.color) iconEl.style.color = this.data.color;
            if (this.data.padding) iconEl.style.padding = isNaN(this.data.padding) ? this.data.padding : `${this.data.padding}px`;

            container.appendChild(iconEl);
            return container;
        }

        const wrapper = document.createElement('div');
        wrapper.classList.add('pw-editor-icon-row');

        const previewBadge = document.createElement('div');
        previewBadge.classList.add('pw-editor-icon-badge');
        const previewIcon = document.createElement('iconify-icon');
        previewBadge.appendChild(previewIcon);
        wrapper.appendChild(previewBadge);
        this.nodes.previewIcon = previewIcon;
        this.nodes.previewBadge = previewBadge;

        // Icon name input
        const inputIcon = document.createElement('input');
        inputIcon.type = 'text';
        inputIcon.classList.add('pw-editor-ctrl');
        inputIcon.placeholder = typeof __ === 'function' ? __('plugins.icon_placeholder') : 'mdi:emoticon';
        inputIcon.value = this.data.icon;
        inputIcon.spellcheck = false;
        inputIcon.addEventListener('input', () => {
            this.data.icon = inputIcon.value.trim();
            this._updatePreview();
        });
        wrapper.appendChild(inputIcon);
        this.nodes.inputIcon = inputIcon;

        // size input
        const inputSize = document.createElement('input');
        inputSize.type = 'text';
        inputSize.classList.add('pw-editor-ctrl');
        inputSize.placeholder = typeof __ === 'function' ? __('plugins.icon_size') : 'Size';
        inputSize.value = this.data.size;
        inputSize.title = typeof __ === 'function' ? __('plugins.icon_size') : 'Size (e.g. 32px)';
        inputSize.addEventListener('input', () => {
            this.data.size = inputSize.value.trim();
            this._updatePreview();
        });
        wrapper.appendChild(inputSize);
        this.nodes.inputSize = inputSize;

        // Padding input
        const inputPadding = document.createElement('input');
        inputPadding.type = 'text';
        inputPadding.classList.add('pw-editor-ctrl');
        inputPadding.placeholder = typeof __ === 'function' ? __('plugins.icon_padding') : 'Padding';
        inputPadding.value = this.data.padding;
        inputPadding.title = typeof __ === 'function' ? __('plugins.icon_padding') : 'Padding (e.g. 4px)';
        inputPadding.addEventListener('input', () => {
            this.data.padding = inputPadding.value.trim();
            this._updatePreview();
        });
        wrapper.appendChild(inputPadding);
        this.nodes.inputPadding = inputPadding;

        // Color picker
        const colorWrap = document.createElement('div');
        colorWrap.style.display = 'flex';
        colorWrap.style.alignItems = 'center';

        const colorPicker = document.createElement('input');
        colorPicker.type = 'color';
        colorPicker.classList.add('pw-editor-ctrl');
        colorPicker.title = typeof __ === 'function' ? __('plugins.icon_color') : 'Color';
        colorPicker.value = this.data.color && this.data.color.startsWith('#') && this.data.color.length === 7 ? this.data.color : '#58a6ff';
        colorPicker.addEventListener('input', () => {
            this.data.color = colorPicker.value;
            this._updatePreview();
        });
        colorWrap.appendChild(colorPicker);

        const clearColorBtn = document.createElement('button');
        clearColorBtn.type = 'button';
        clearColorBtn.classList.add('pw-btn');
        clearColorBtn.title = 'Reset color (inherit)';
        clearColorBtn.innerHTML = '<iconify-icon icon="mdi:close"></iconify-icon>';
        clearColorBtn.addEventListener('click', () => {
            this.data.color = '';
            this._updatePreview();
        });
        colorWrap.appendChild(clearColorBtn);
        wrapper.appendChild(colorWrap);

        this.nodes.colorPicker = colorPicker;
        this.nodes.clearColorBtn = clearColorBtn;

        // Alignment dropdown
        const selectAlign = document.createElement('select');
        selectAlign.classList.add('pw-editor-ctrl');
        selectAlign.title = typeof __ === 'function' ? __('plugins.icon_align') : 'Alignment';
        [
            { value: 'left', label: typeof __ === 'function' ? __('plugins.text_align_left') : 'Left' },
            { value: 'center', label: typeof __ === 'function' ? __('plugins.text_align_center') : 'Center' },
            { value: 'right', label: typeof __ === 'function' ? __('plugins.text_align_right') : 'Right' }
        ].forEach(opt => {
            const el = document.createElement('option');
            el.value = opt.value;
            el.textContent = opt.label;
            if (this.data.align === opt.value) el.selected = true;
            selectAlign.appendChild(el);
        });
        selectAlign.addEventListener('change', () => {
            this.data.align = selectAlign.value;
            this._updatePreview();
        });
        wrapper.appendChild(selectAlign);
        this.nodes.selectAlign = selectAlign;

        // link to iconify page to find icons
        const browseLink = document.createElement('a');
        browseLink.href = 'https://icon-sets.iconify.design/';
        browseLink.target = '_blank';
        browseLink.rel = 'noopener noreferrer';
        browseLink.classList.add('pw-btn');
        browseLink.title = typeof __ === 'function' ? __('plugins.icon_browse_hint') : 'Search icons on iconify.design';
        browseLink.innerHTML = '<iconify-icon icon="mdi:open-in-new"></iconify-icon>';
        wrapper.appendChild(browseLink);

        this.nodes.wrapper = wrapper;
        this._updatePreview();

        return wrapper;
    }

    save() {
        return {
            icon: (this.data.icon || '').trim(),
            size: (this.data.size || '').trim(),
            color: (this.data.color || '').trim(),
            padding: (this.data.padding || '').trim(),
            align: this.data.align || 'left'
        };
    }

    validate(savedData) {
        return !!(savedData.icon && savedData.icon.trim() !== '');
    }
}

class InlineIconTool {
    static get isInline() {
        return true;
    }

    static get title() {
        return 'Icon';
    }

    static get sanitize() {
        return {
            'iconify-icon': {
                icon: true,
                style: true,
                class: true
            }
        };
    }

    constructor({ api }) {
        this.api = api;
        this.button = null;
        this._popover = null;
        this._currentSelectedIcon = null;

        this.iconClasses = {
            base: this.api.styles.inlineToolButton,
            active: this.api.styles.inlineToolButtonActive
        };

        // Initialize editor click listener to allow editing icon by clicking it
        InlineIconTool._initGlobalClickListener(this);
    }

    static _initGlobalClickListener(instance) {
        if (window._pwInlineIconClickListenerAttached) return;
        window._pwInlineIconClickListenerAttached = true;

        document.addEventListener('click', (e) => {
            const icon = e.target.closest('iconify-icon.pw-inline-icon');
            if (!icon) return;

            e.preventDefault();
            e.stopPropagation();

            instance._openActionPanel(null, icon);
        });
    }

    render() {
        this.button = document.createElement('button');
        this.button.type = 'button';
        this.button.classList.add(this.iconClasses.base);
        this.button.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M5.5 2C3.56 2 2 3.56 2 5.5v13C2 20.44 3.56 22 5.5 22H16l6-6V5.5C22 3.56 20.44 2 18.5 2zm.25 2h12.5A1.75 1.75 0 0 1 20 5.75V15h-1.5c-1.94 0-3.5 1.56-3.5 3.5V20H5.75A1.75 1.75 0 0 1 4 18.25V5.75A1.75 1.75 0 0 1 5.75 4m8.69 2.77c-.16 0-.32.02-.47.06c-.94.26-1.47 1.22-1.23 2.17c.05.15.12.3.21.44l3.23-.88q0-.255-.06-.51c-.21-.75-.9-1.28-1.68-1.28M8.17 8.5c-.17 0-.32 0-.47.05c-.93.26-1.48 1.22-1.23 2.15c.03.16.12.3.21.46l3.23-.88c0-.17-.02-.34-.06-.5A1.72 1.72 0 0 0 8.17 8.5m8.55 2.76l-9.13 2.51a5.27 5.27 0 0 0 5.36 1.64a5.27 5.27 0 0 0 3.77-4.15" /></svg>';
        return this.button;
    }

    _findSelectedIcon(range) {
        const sel = window.getSelection();
        if (!sel || sel.rangeCount === 0) return null;

        const r = range || sel.getRangeAt(0);
        let node = sel.anchorNode;
        while (node && node !== document.body) {
            if (node.nodeName === 'ICONIFY-ICON' && node.classList.contains('pw-inline-icon')) return node;
            node = node.parentNode;
        }

        const container = r.commonAncestorContainer;
        if (container.nodeName === 'ICONIFY-ICON' && container.classList.contains('pw-inline-icon')) return container;

        const parentEl = container.nodeType === Node.ELEMENT_NODE ? container : container.parentNode;
        if (parentEl) {
            if (parentEl.nodeName === 'ICONIFY-ICON' && parentEl.classList.contains('pw-inline-icon')) return parentEl;

            const icons = parentEl.querySelectorAll('iconify-icon.pw-inline-icon');
            for (const ic of icons) {
                if (r.intersectsNode && r.intersectsNode(ic)) {
                    return ic;
                }
            }
        }

        return null;
    }

    checkState() {
        const iconNode = this._findSelectedIcon();
        this.button.classList.toggle(this.iconClasses.active, !!iconNode);
        return !!iconNode;
    }

    surround(range) {
        if (!range) return;

        const iconNode = this._findSelectedIcon(range);
        this._openActionPanel(range, iconNode);
    }

    _openActionPanel(range, targetIcon = null) {
        if (this._popover) {
            this._popover.remove();
            this._popover = null;
        }

        const existing = targetIcon || this._findSelectedIcon(range);
        const currentIcon = existing ? (existing.getAttribute('icon') || '') : 'mdi:emoticon';
        const currentSize = existing ? (existing.style.fontSize || '') : '';
        const currentColor = existing ? (existing.style.color || '') : '';

        const popover = document.createElement('div');
        popover.classList.add('pw-inline-icon-popover');

        popover.innerHTML = `
            <div class="pw-inline-icon-popover-header">
                <span class="pw-editor-toolbar-label">${existing ? 'Edit Icon' : 'Insert Icon'}</span>
                ${existing ? '<button type="button" class="pw-btn pw-btn-danger pw-inline-icon-btn-remove" title="Remove icon"><iconify-icon icon="mdi:trash-can-outline"></iconify-icon></button>' : ''}
            </div>
            <div class="pw-inline-icon-popover-row">
                <input type="text" class="pw-editor-ctrl pw-inline-icon-id" placeholder="mdi:emoticon" value="${currentIcon}" spellcheck="false" />
                <input type="text" class="pw-editor-ctrl pw-inline-icon-size" placeholder="Size" value="${currentSize}" title="Size (e.g. 1.2em)" style="width: 60px;" />
                <div style="display:flex;align-items:center;">
                    <input type="color" class="pw-editor-ctrl pw-inline-icon-color" title="Color" value="${currentColor && currentColor.startsWith('#') && currentColor.length === 7 ? currentColor : '#58a6ff'}" />
                    <button type="button" class="pw-btn pw-inline-icon-color-reset" title="Reset color (inherit)" style="${currentColor ? '' : 'display:none;'}"><iconify-icon icon="mdi:close"></iconify-icon></button>
                </div>
            </div>
            <div class="pw-dialog-actions">
                <button type="button" class="pw-btn pw-btn-secondary pw-inline-icon-btn-cancel">Cancel</button>
                <button type="button" class="pw-btn pw-btn-primary pw-inline-icon-btn-save">${existing ? 'Update' : 'Insert'}</button>
            </div>
        `;

        document.body.appendChild(popover);
        this._popover = popover;

        const anchorEl = existing || this.button;
        const rect = anchorEl.getBoundingClientRect();
        popover.style.position = 'fixed';
        popover.style.zIndex = '99999';
        popover.style.top = `${Math.min(window.innerHeight - 120, rect.bottom + 6)}px`;
        popover.style.left = `${Math.max(10, Math.min(window.innerWidth - 320, rect.left - 50))}px`;

        const idInput = popover.querySelector('.pw-inline-icon-id');
        const sizeInput = popover.querySelector('.pw-inline-icon-size');
        const colorInput = popover.querySelector('.pw-inline-icon-color');
        const colorResetBtn = popover.querySelector('.pw-inline-icon-color-reset');
        const saveBtn = popover.querySelector('.pw-inline-icon-btn-save');
        const cancelBtn = popover.querySelector('.pw-inline-icon-btn-cancel');
        const removeBtn = popover.querySelector('.pw-inline-icon-btn-remove');

        let activeColor = currentColor;

        colorInput.addEventListener('input', () => {
            activeColor = colorInput.value;
            if (colorResetBtn) colorResetBtn.style.display = 'inline-flex';
        });

        if (colorResetBtn) {
            colorResetBtn.addEventListener('click', () => {
                activeColor = '';
                colorResetBtn.style.display = 'none';
            });
        }

        setTimeout(() => idInput.focus(), 50);

        const closePopover = () => {
            if (this._popover) {
                this._popover.remove();
                this._popover = null;
            }
        };

        const triggerEditorChange = (el) => {
            const blockEl = el.closest('.ce-block');
            if (blockEl) {
                blockEl.dispatchEvent(new Event('input', { bubbles: true }));
            }
            if (typeof hasUnsavedChanges !== 'undefined') hasUnsavedChanges = true;
            if (typeof autoSaveTimeout !== 'undefined') clearTimeout(autoSaveTimeout);
            if (typeof saveCurrentDraft === 'function') {
                autoSaveTimeout = setTimeout(() => saveCurrentDraft(true), 1000);
            }
        };

        const applyAction = () => {
            const iconId = idInput.value.trim();
            if (!iconId) {
                closePopover();
                return;
            }

            const size = sizeInput.value.trim();

            if (existing) {
                existing.setAttribute('icon', iconId);
                if (size) existing.style.fontSize = isNaN(size) ? size : `${size}px`;
                else existing.style.fontSize = '';
                if (activeColor) existing.style.color = activeColor;
                else existing.style.color = '';
                triggerEditorChange(existing);
            } else if (range) {
                const iconTag = document.createElement('iconify-icon');
                iconTag.setAttribute('icon', iconId);
                iconTag.classList.add('pw-inline-icon');
                if (size) iconTag.style.fontSize = isNaN(size) ? size : `${size}px`;
                if (activeColor) iconTag.style.color = activeColor;

                range.insertNode(iconTag);
                range.collapse(false);
                triggerEditorChange(iconTag);
            }

            closePopover();
        };

        saveBtn.addEventListener('click', applyAction);
        idInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                applyAction();
            } else if (e.key === 'Escape') {
                closePopover();
            }
        });

        cancelBtn.addEventListener('click', closePopover);

        if (removeBtn && existing) {
            removeBtn.addEventListener('click', () => {
                const parent = existing.parentNode;
                existing.remove();
                if (parent) triggerEditorChange(parent);
                closePopover();
            });
        }
    }
}

