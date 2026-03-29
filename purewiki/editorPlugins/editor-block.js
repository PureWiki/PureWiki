/**
 * PureWiki - Block Tool
 *
 * Block container tool for Editor.js — styled container with nested Editor.js content.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */
class BlockTool {
    static get isReadOnlySupported() {
        return true;
    }

    static get toolbox() {
        return {
            title: typeof __ === 'function' ? __('plugins.block') : 'Block',
            icon: '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2m0 16H5V5h14z"/></svg>'
        };
    }

    constructor({ data, api, readOnly }) {
        this.api = api;
        this.readOnly = readOnly;
        this.data = {
            bgColor: data.bgColor || '',
            textColor: data.textColor || '',
            padding: data.padding || '0',
            margin: data.margin || '0',
            fullsize: typeof data.fullsize === 'boolean' ? data.fullsize : false,
            link: data.link || '',
            alignH: data.alignH || 'left',
            alignV: data.alignV || 'top',
            minHeight: parseInt(data.minHeight, 10) || 0,
            blocks: data.blocks || []
        };
        this.wrapper = null;
        this.contentArea = null;
        this._nestedEditor = null;
    }

    render() {
        this.wrapper = document.createElement('div');
        this.wrapper.classList.add('pw-editor-block');

        if (!this.readOnly) {
            const settingsBtn = document.createElement('button');
            settingsBtn.type = 'button';
            settingsBtn.className = 'pw-btn pw-editor-block-settings-btn';
            settingsBtn.innerHTML = '<iconify-icon icon="mdi:cog-outline"></iconify-icon> ' + (typeof __ === 'function' ? __('plugins.block_settings_btn') : 'Settings');
            settingsBtn.title = typeof __ === 'function' ? __('plugins.block_settings') : 'Block Settings';
            settingsBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                this._openSettingsDialog();
            });
            this.wrapper.appendChild(settingsBtn);
        }

        this.contentArea = document.createElement('div');
        this.contentArea.classList.add('pw-editor-block-content');

        const editorHolder = document.createElement('div');
        const holderId = 'pw-block-editor-' + Date.now() + '-' + Math.random().toString(36).substr(2, 6);
        editorHolder.id = holderId;
        editorHolder.classList.add('pw-editor-block-editor', 'pw-editor-nested');

        this.contentArea.addEventListener('click', (e) => e.stopPropagation());
        this.contentArea.addEventListener('keydown', (e) => e.stopPropagation());
        this.contentArea.addEventListener('mousedown', (e) => e.stopPropagation());

        this.contentArea.appendChild(editorHolder);
        this.wrapper.appendChild(this.contentArea);

        this._applyStyles();
        this._initNestedEditor(holderId);

        return this.wrapper;
    }

    _openSettingsDialog() {
        const existingOverlay = document.getElementById('pw-block-settings-overlay');
        if (existingOverlay) existingOverlay.remove();

        const overlay = document.createElement('div');
        overlay.id = 'pw-block-settings-overlay';
        overlay.className = 'pw-dialog-overlay pw-show';

        const box = document.createElement('div');
        box.className = 'pw-dialog-box';
        box.style.width = '480px';

        const title = document.createElement('h3');
        title.className = 'pw-dialog-title';
        title.textContent = typeof __ === 'function' ? __('plugins.block_settings') : 'Block Settings';
        box.appendChild(title);

        const form = document.createElement('div');
        form.className = 'pw-editor-block-dialog-form';

        const colorRow = this._dialogRow(typeof __ === 'function' ? __('plugins.block_bg_color') : 'Background Color');
        const bgWrap = this._colorPicker(this.data.bgColor);
        colorRow.appendChild(bgWrap.el);
        form.appendChild(colorRow);

        const textColorRow = this._dialogRow(typeof __ === 'function' ? __('plugins.block_text_color') : 'Text Color');
        const tcWrap = this._colorPicker(this.data.textColor);
        textColorRow.appendChild(tcWrap.el);
        form.appendChild(textColorRow);

        const padRow = this._dialogRow(typeof __ === 'function' ? __('plugins.block_padding') : 'Padding');
        const padInput = this._spacingInput(this.data.padding);
        padRow.appendChild(padInput.el);
        form.appendChild(padRow);

        const marRow = this._dialogRow(typeof __ === 'function' ? __('plugins.block_margin') : 'Margin');
        const marInput = this._spacingInput(this.data.margin);
        marRow.appendChild(marInput.el);
        form.appendChild(marRow);

        const mhRow = this._dialogRow(typeof __ === 'function' ? __('plugins.block_min_height') : 'Min Height');
        const mhWrap = document.createElement('div');
        mhWrap.className = 'pw-editor-block-inline-row';
        const mhInput = document.createElement('input');
        mhInput.type = 'number';
        mhInput.min = 0;
        mhInput.max = 800;
        mhInput.step = 10;
        mhInput.value = this.data.minHeight;
        mhInput.className = 'pw-input pw-input--narrow';
        const mhPx = document.createElement('span');
        mhPx.textContent = 'px';
        mhPx.className = 'pw-editor-block-unit';
        mhWrap.appendChild(mhInput);
        mhWrap.appendChild(mhPx);
        mhRow.appendChild(mhWrap);
        form.appendChild(mhRow);

        const fsRow = this._dialogRow('');
        const fsLabel = document.createElement('label');
        fsLabel.className = 'pw-checkbox-label';
        const fsCheck = document.createElement('input');
        fsCheck.type = 'checkbox';
        fsCheck.checked = this.data.fullsize;
        fsCheck.className = 'pw-checkbox';
        const fsText = document.createElement('span');
        fsText.textContent = typeof __ === 'function' ? __('plugins.fullsize') : 'Fullsize (fill parent, e.g. Grid cell)';
        fsLabel.appendChild(fsCheck);
        fsLabel.appendChild(fsText);
        fsRow.appendChild(fsLabel);
        form.appendChild(fsRow);

        const hRow = this._dialogRow(typeof __ === 'function' ? __('plugins.block_h_align') : 'Horizontal Align');
        const hSeg = this._dialogSegmented(['left', 'center', 'right'], this.data.alignH);
        hRow.appendChild(hSeg.el);
        form.appendChild(hRow);

        const vRow = this._dialogRow(typeof __ === 'function' ? __('plugins.block_v_align') : 'Vertical Align');
        const vSeg = this._dialogSegmented(['top', 'center', 'bottom'], this.data.alignV);
        vRow.appendChild(vSeg.el);
        form.appendChild(vRow);

        const linkRow = this._dialogRow(typeof __ === 'function' ? __('plugins.block_link') : 'Link (optional)');
        const linkInput = document.createElement('input');
        linkInput.type = 'text';
        linkInput.placeholder = typeof __ === 'function' ? __('plugins.link_placeholder') : 'https://...';
        linkInput.value = this.data.link;
        linkInput.className = 'pw-input';
        linkRow.appendChild(linkInput);
        form.appendChild(linkRow);

        box.appendChild(form);

        const actions = document.createElement('div');
        actions.className = 'pw-dialog-actions';

        const cancelBtn = document.createElement('button');
        cancelBtn.className = 'pw-btn';
        cancelBtn.textContent = typeof __ === 'function' ? __('common.cancel') : 'Cancel';
        cancelBtn.addEventListener('click', () => overlay.remove());

        const applyBtn = document.createElement('button');
        applyBtn.className = 'pw-btn pw-btn-primary';
        applyBtn.textContent = typeof __ === 'function' ? __('common.apply') : 'Apply';
        applyBtn.addEventListener('click', () => {
            this.data.bgColor = bgWrap.getValue();
            this.data.textColor = tcWrap.getValue();
            this.data.padding = padInput.getValue();
            this.data.margin = marInput.getValue();
            this.data.minHeight = parseInt(mhInput.value, 10) || 0;
            this.data.fullsize = fsCheck.checked;
            this.data.alignH = hSeg.getValue();
            this.data.alignV = vSeg.getValue();
            this.data.link = linkInput.value.trim();

            this._applyStyles();

            if (typeof hasUnsavedChanges !== 'undefined') hasUnsavedChanges = true;
            if (typeof autoSaveTimeout !== 'undefined') clearTimeout(autoSaveTimeout);
            if (typeof saveCurrentDraft === 'function') {
                autoSaveTimeout = setTimeout(() => saveCurrentDraft(true), 1000);
            }

            overlay.remove();
        });

        actions.appendChild(cancelBtn);
        actions.appendChild(applyBtn);
        box.appendChild(actions);

        overlay.addEventListener('mousedown', (e) => {
            if (e.target === overlay) overlay.remove();
        });

        overlay.appendChild(box);
        document.body.appendChild(overlay);
    }

    _colorPicker(initialColor) {
        const wrap = document.createElement('div');
        wrap.className = 'pw-editor-block-color-row';
        const input = document.createElement('input');
        input.type = 'color';
        input.value = initialColor || '#ffffff';
        input.className = 'pw-editor-block-color';
        const hex = document.createElement('span');
        hex.className = 'pw-editor-block-color-hex';
        hex.textContent = initialColor || 'none';
        input.addEventListener('input', () => { hex.textContent = input.value; });
        const clear = document.createElement('button');
        clear.type = 'button';
        clear.className = 'pw-btn pw-editor-block-clear-btn';
        clear.textContent = typeof __ === 'function' ? __('common.clear') : 'Clear';
        clear.addEventListener('click', () => { hex.textContent = 'none'; input.value = '#ffffff'; });
        wrap.appendChild(input);
        wrap.appendChild(hex);
        wrap.appendChild(clear);
        return {
            el: wrap,
            getValue: () => hex.textContent === 'none' ? '' : input.value
        };
    }

    _spacingInput(initialValue) {
        const wrap = document.createElement('div');
        wrap.className = 'pw-editor-block-inline-row';
        const input = document.createElement('input');
        input.type = 'text';
        input.value = initialValue || '0';
        input.placeholder = typeof __ === 'function' ? __('plugins.padding_placeholder') : 'e.g. 8 or 8 16 8 16';
        input.className = 'pw-input';
        input.style.maxWidth = '200px';
        const hint = document.createElement('span');
        hint.className = 'pw-editor-block-unit';
        hint.textContent = typeof __ === 'function' ? __('plugins.padding_hint') : 'px (top right bottom left)';
        wrap.appendChild(input);
        wrap.appendChild(hint);
        return {
            el: wrap,
            getValue: () => input.value.trim() || '0'
        };
    }

    _dialogRow(label) {
        const row = document.createElement('div');
        row.className = 'pw-setting-field';
        if (label) {
            const l = document.createElement('label');
            l.textContent = label;
            l.className = 'pw-field-label';
            row.appendChild(l);
        }
        return row;
    }

    _dialogSegmented(options, active) {
        let current = active;
        const seg = document.createElement('div');
        seg.className = 'pw-editor-block-segmented';
        options.forEach(opt => {
            const btn = document.createElement('button');
            btn.type = 'button';
            const labelMap = {
                left: typeof __ === 'function' ? __('plugins.block_align_left') : 'Left',
                center: typeof __ === 'function' ? __('plugins.block_align_center') : 'Center',
                right: typeof __ === 'function' ? __('plugins.block_align_right') : 'Right',
                top: typeof __ === 'function' ? __('plugins.block_align_top') : 'Top',
                bottom: typeof __ === 'function' ? __('plugins.block_align_bottom') : 'Bottom'
            };
            btn.textContent = labelMap[opt] || (opt.charAt(0).toUpperCase() + opt.slice(1));
            btn.className = 'pw-editor-block-seg-btn';
            if (opt === active) btn.classList.add('pw-active');
            btn.addEventListener('click', () => {
                seg.querySelectorAll('.pw-editor-block-seg-btn').forEach(b => b.classList.remove('pw-active'));
                btn.classList.add('pw-active');
                current = opt;
            });
            seg.appendChild(btn);
        });
        return {
            el: seg,
            getValue: () => current
        };
    }

    _spacingToCss(val) {
        if (!val || val === '0') return '';
        const parts = val.trim().split(/\s+/).map(v => parseInt(v, 10) || 0);
        if (parts.every(p => p === 0)) return '';
        return parts.map(p => p + 'px').join(' ');
    }

    _applyStyles() {
        if (!this.contentArea) return;
        const s = this.contentArea.style;

        s.backgroundColor = this.data.bgColor || '';
        s.color = this.data.textColor || '';
        s.padding = this._spacingToCss(this.data.padding);
        s.margin = this._spacingToCss(this.data.margin);
        s.minHeight = this.data.minHeight > 0 ? this.data.minHeight + 'px' : '';

        if (this.data.fullsize) {
            s.width = '100%';
            s.height = '100%';
        } else {
            s.width = '';
            s.height = '';
        }

        s.display = 'flex';
        s.flexDirection = 'column';

        const hMap = { left: 'flex-start', center: 'center', right: 'flex-end' };
        s.alignItems = hMap[this.data.alignH] || 'flex-start';

        const vMap = { top: 'flex-start', center: 'center', bottom: 'flex-end' };
        s.justifyContent = vMap[this.data.alignV] || 'flex-start';
    }

    _initNestedEditor(holderId) {
        const tools = window.PW_EDITOR_TOOLS || {
            header: { class: Header, config: { levels: [2, 3, 4, 5, 6], defaultLevel: 3 } },
            list: { class: EditorjsList, inlineToolbar: true },
            delimiter: Delimiter
        };

        this._nestedEditor = new EditorJS({
            holder: holderId,
            data: { blocks: this.data.blocks || [] },
            placeholder: typeof __ === 'function' ? __('plugins.block_content_placeholder') : 'Block content…',
            minHeight: 0,
            i18n: window.PW_EDITOR_I18N_CONFIG || undefined,
            tools: tools,
            readOnly: this.readOnly,
            onChange: () => {
                if (window.pwEditorInitializing) return;
                if (typeof hasUnsavedChanges !== 'undefined') hasUnsavedChanges = true;
                if (typeof autoSaveTimeout !== 'undefined') clearTimeout(autoSaveTimeout);
                if (typeof saveCurrentDraft === 'function') {
                    autoSaveTimeout = setTimeout(() => saveCurrentDraft(true), 1000);
                }
            }
        });
    }

    async save() {
        let blocks = [];
        if (this._nestedEditor) {
            try {
                const output = await this._nestedEditor.save();
                blocks = output.blocks || [];
            } catch (_) {
                blocks = this.data.blocks || [];
            }
        }
        return {
            bgColor: this.data.bgColor || '',
            textColor: this.data.textColor || '',
            padding: this.data.padding || '0',
            margin: this.data.margin || '0',
            fullsize: !!this.data.fullsize,
            link: this.data.link || '',
            alignH: this.data.alignH || 'left',
            alignV: this.data.alignV || 'top',
            minHeight: this.data.minHeight || 0,
            blocks: blocks
        };
    }

    destroy() {
        if (this._nestedEditor) {
            try { this._nestedEditor.destroy(); } catch (_) {}
            this._nestedEditor = null;
        }
    }
}
