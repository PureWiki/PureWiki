/**
 * PureWiki - Callout Tool
 *
 * Callout block tool for Editor.js.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */
class CalloutTool {
    static get isReadOnlySupported() {
        return true;
    }

    constructor({ data, api, readOnly }) {
        this.api = api;
        this.readOnly = readOnly;

        this.styles = [
            { value: 'info',      label: 'Info',      icon: 'mdi:information-outline' },
            { value: 'warning',   label: 'Warning',   icon: 'mdi:alert-outline' },
            { value: 'important', label: 'Important', icon: 'mdi:alert-circle-outline' }
        ];

        const defaultStyle = this.styles[0];

        this.data = {
            header:   data.header   || '',
            text:     data.text     || '',
            style:    data.style    || defaultStyle.value,
            showIcon: typeof data.showIcon === 'boolean' ? data.showIcon : true,
            icon:     data.icon     || ''
        };
    }

    render() {
        const wrapper = document.createElement('div');
        wrapper.classList.add('pw-editor-callout', 'pw-editor-callout-' + this.data.style);

        const content = document.createElement('div');
        content.classList.add('pw-editor-callout-body');

        const headerEl = document.createElement('div');
        headerEl.contentEditable = !this.readOnly;
        headerEl.dataset.placeholder = typeof __ === 'function' ? __('plugins.callout_header_placeholder') : 'Header (optional)';
        headerEl.classList.add('pw-editor-callout-header');
        headerEl.innerHTML = this.data.header;
        headerEl.addEventListener('input', () => { 
            if (window.pwEditorInitializing) return;
            this.data.header = headerEl.innerHTML; 
        });
        content.appendChild(headerEl);

        const textEl = document.createElement('div');
        textEl.contentEditable = !this.readOnly;
        textEl.dataset.placeholder = typeof __ === 'function' ? __('plugins.callout_text_placeholder') : 'Callout text…';
        textEl.classList.add('pw-editor-callout-text');
        textEl.innerHTML = this.data.text;
        textEl.addEventListener('input', () => { 
            if (window.pwEditorInitializing) return;
            this.data.text = textEl.innerHTML; 
        });
        content.appendChild(textEl);

        wrapper.appendChild(content);

        const toolbar = document.createElement('div');
        toolbar.classList.add('pw-editor-callout-toolbar');

        const select = document.createElement('select');
        select.classList.add('pw-editor-callout-select', 'pw-editor-ctrl');
        this.styles.forEach(s => {
            const opt = document.createElement('option');
            opt.value = s.value;
            opt.textContent = s.label;
            if (this.data.style === s.value) opt.selected = true;
            select.appendChild(opt);
        });
        if (this.readOnly) select.disabled = true;
        select.addEventListener('change', () => {
            this.data.style = select.value;
            wrapper.className = 'pw-editor-callout pw-editor-callout-' + this.data.style;
            if (!this.data.icon) this._updatePreviewIcon();
        });
        toolbar.appendChild(select);

        const iconLabel = document.createElement('label');
        iconLabel.classList.add('pw-editor-callout-icon-label');
        const iconCheck = document.createElement('input');
        iconCheck.type = 'checkbox';
        iconCheck.checked = this.data.showIcon;
        if (this.readOnly) iconCheck.disabled = true;
        iconCheck.addEventListener('change', () => {
            this.data.showIcon = iconCheck.checked;
            iconNameInput.style.display = iconCheck.checked ? '' : 'none';
            previewIcon.style.display = iconCheck.checked ? '' : 'none';
        });
        iconLabel.appendChild(iconCheck);
        iconLabel.appendChild(document.createTextNode('Icon'));
        toolbar.appendChild(iconLabel);

        const iconNameInput = document.createElement('input');
        iconNameInput.type = 'text';
        iconNameInput.placeholder = typeof __ === 'function' ? __('plugins.callout_icon_placeholder') : 'Iconify name (optional)';
        iconNameInput.value = this.data.icon;
        iconNameInput.classList.add('pw-editor-callout-icon-input', 'pw-editor-ctrl');
        if (!this.data.showIcon) iconNameInput.style.display = 'none';
        if (this.readOnly) iconNameInput.readOnly = true;
        iconNameInput.addEventListener('input', () => {
            this.data.icon = iconNameInput.value.trim();
            this._updatePreviewIcon();
        });
        toolbar.appendChild(iconNameInput);

        const previewIcon = document.createElement('iconify-icon');
        previewIcon.classList.add('pw-editor-callout-preview-icon');
        if (!this.data.showIcon) previewIcon.style.display = 'none';
        this._previewIcon = previewIcon;
        this._updatePreviewIcon();
        toolbar.appendChild(previewIcon);

        wrapper.appendChild(toolbar);
        return wrapper;
    }

    _updatePreviewIcon() {
        const resolved = this.data.icon || this.styles.find(s => s.value === this.data.style)?.icon || 'mdi:information-outline';
        this._previewIcon.setAttribute('icon', resolved);
    }

    save() {
        return {
            header:   this.data.header,
            text:     this.data.text,
            style:    this.data.style,
            showIcon: this.data.showIcon,
            icon:     this.data.icon
        };
    }

    static get toolbox() {
        return {
            title: typeof __ === 'function' ? __('plugins.callout') : 'Callout',
            icon: '<svg width="20" height="20" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path fill="currentColor" d="M11 7h2v2h-2zm0 4h2v6h-2zm1-9C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2m0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8"/></svg>'
        };
    }
}
