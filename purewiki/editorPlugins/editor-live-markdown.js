/**
 * PureWiki - Live Markdown Tool
 *
 * Real-time Markdown editor for Editor.js.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

class LiveMarkdownTool {
    static get isReadOnlySupported() {
        return true;
    }

    static get toolbox() {
        return {
            title: typeof __ === 'function' ? __('plugins.live_markdown') : 'Live Markdown',
            icon: `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M14.6 15c.95-1.12 1.4-2.4 1.4-3c0-2.21-1.79-4-4-4H8v2h4c1.1 0 2 .9 2 2s-.9 2-2 2h-1v-2H9v4h1c1.5 0 2.87-.84 3.6-2zm-5.2 0A3.99 3.99 0 0 1 8 13.5v-3C6.5 10.5 5 11.84 5 13.5c0 1.12.45 2.4 1.4 3h1.2v-2H7v-2h1V9H7c-2.21 0-4 1.79-4 4s1.79 4 4 4h5v-2H9.4zM20 12l-4-4v3h-4v2h4v3l4-4z"/></svg>` // Download/Link representation
        };
    }

    constructor({ data, config, api, readOnly }) {
        this.api = api;
        this.readOnly = readOnly;
        this.data = {
            url: data.url || '',
            header: data.header || ''
        };

        this.wrapper = undefined;
        this.input = undefined;
        this.headerInput = undefined;
        this.validateBtn = undefined;
        this.statusMsg = undefined;
    }

    render() {
        this.wrapper = document.createElement('div');
        this.wrapper.classList.add('pw-editor-livemd-wrapper');

        const title = document.createElement('div');
        title.classList.add('pw-editor-livemd-title');
        title.innerHTML = typeof __ === 'function' ? __('plugins.live_markdown_desc') : 'Live Markdown <span style="font-weight: normal; font-size: 0.9em; color: var(--pw-text-muted);">(Embed .md from URL, optionally filtered by header). Pullrate depends on caching settings.</span>';
        this.wrapper.appendChild(title);

        const inputGroup = document.createElement('div');
        inputGroup.classList.add('pw-editor-livemd-input-group');

        this.input = document.createElement('input');
        this.input.type = 'url';
        this.input.classList.add('pw-editor-livemd-input');
        this.input.placeholder = typeof __ === 'function' ? __('plugins.live_markdown_url_placeholder') : 'https://raw.githubusercontent.com/...';
        this.input.value = this.data.url;

        if (this.readOnly) {
            this.input.disabled = true;
        }

        this.validateBtn = document.createElement('button');
        this.validateBtn.classList.add('pw-btn', 'pw-btn-secondary', 'pw-editor-livemd-btn');
        this.validateBtn.textContent = typeof __ === 'function' ? __('plugins.live_markdown_validate') : 'Validate';
        this.validateBtn.type = 'button';

        if (this.readOnly) {
            this.validateBtn.disabled = true;
        }

        this.statusMsg = document.createElement('div');
        this.statusMsg.classList.add('pw-editor-livemd-status');

        inputGroup.appendChild(this.input);
        inputGroup.appendChild(this.validateBtn);

        this.wrapper.appendChild(inputGroup);

        this.headerInput = document.createElement('input');
        this.headerInput.type = 'text';
        this.headerInput.classList.add('pw-editor-livemd-input', 'pw-editor-livemd-header-input');
        this.headerInput.placeholder = typeof __ === 'function' ? __('plugins.live_markdown_header_placeholder') : '### Section Header (optional)';
        this.headerInput.value = this.data.header;

        if (this.readOnly) {
            this.headerInput.disabled = true;
        }

        this.wrapper.appendChild(this.headerInput);
        this.wrapper.appendChild(this.statusMsg);

        this.validateBtn.addEventListener('click', () => {
             this._validateUrl();
        });

        if (this.data.url && !window.pwEditorInitializing) {
            this._validateUrl();
        }

        return this.wrapper;
    }

    save(blockContent) {
        return {
            url: this.input.value.trim(),
            header: this.headerInput.value.trim()
        };
    }

    validate(savedData) {
        if (!savedData.url || savedData.url.trim() === '') {
            return false;
        }
        return true;
    }

    async _validateUrl() {
        const url = this.input.value.trim();
        if (!url) {
            this._setStatus(typeof __ === 'function' ? __('plugins.live_markdown_enter_url') : 'Please enter a URL.', 'error');
            return;
        }

        this.validateBtn.disabled = true;
        this.validateBtn.textContent = typeof __ === 'function' ? __('plugins.live_markdown_checking') : 'Checking...';
        this._setStatus('', '');

        try {
            // client-side validation; might fail on CORS
            const response = await fetch(url, { method: 'HEAD' });

            if (response.ok) {
                this._setStatus(typeof __ === 'function' ? __('plugins.live_markdown_valid') : 'URL is valid.', 'success');
            } else {
                this._setStatus(typeof __ === 'function' ? __('plugins.live_markdown_status_error', response.status) : `URL returned status ${response.status}`, 'warning');
            }
        } catch (e) {
             this._setStatus(typeof __ === 'function' ? __('plugins.live_markdown_cors_error') : 'Could not verify (CORS or network error). Backend might still load it.', 'warning');
        } finally {
            this.validateBtn.disabled = false;
            this.validateBtn.textContent = typeof __ === 'function' ? __('plugins.live_markdown_validate') : 'Validate';
        }
    }

    _setStatus(message, type) {
        this.statusMsg.textContent = message;
        this.statusMsg.className = 'pw-editor-livemd-status';
        if (type === 'error') this.statusMsg.classList.add('pw-editor-livemd-error');
        if (type === 'warning') this.statusMsg.classList.add('pw-editor-livemd-warning');
        if (type === 'success') this.statusMsg.classList.add('pw-editor-livemd-success');
    }
}
