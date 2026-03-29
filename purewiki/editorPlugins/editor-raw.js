/**
 * PureWiki - Raw HTML Tool
 *
 * Simple Raw HTML editor for Editor.js.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */
class RawTool {
    static get isReadOnlySupported() {
        return true;
    }

    static get toolbox() {
        return {
            title: typeof __ === 'function' ? __('plugins.raw') : 'Raw HTML',
            icon: `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M14.6 16.6l4.6-4.6l-4.6-4.6L16 6l6 6l-6 6l-1.4-1.4M9.4 16.6L4.8 12l4.6-4.6L8 6l-6 6l6 6l1.4-1.4z"/></svg>`
        };
    }

    constructor({ data, config, api, readOnly }) {
        this.api = api;
        this.readOnly = readOnly;
        this.data = {
            html: data.html || ''
        };
        this.wrapper = undefined;
        this.textarea = undefined;
    }

    render() {
        this.wrapper = document.createElement('div');
        this.wrapper.classList.add('pw-editor-raw-wrapper');

        this.title = document.createElement('div');
        this.title.classList.add('pw-editor-raw-title');
        this.title.textContent = typeof __ === 'function' ? __('plugins.raw_title') : 'Raw HTML:';
        this.wrapper.appendChild(this.title);

        this.textarea = document.createElement('textarea');
        this.textarea.classList.add('ce-rawtool__textarea');
        this.textarea.placeholder = typeof __ === 'function' ? __('plugins.raw_placeholder') : 'Enter RAW HTML...';
        this.textarea.value = this.data.html;

        if (this.readOnly) {
            this.textarea.disabled = true;
        }

        this.textarea.addEventListener('input', () => {
            if (!CSS.supports('field-sizing', 'content')) {
                this.textarea.style.height = 'auto';
                this.textarea.style.height = (this.textarea.scrollHeight) + 'px';
            }
        });

        this.textarea.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.stopPropagation();
            }
            if (e.key === 'Backspace') {
                if (this.textarea.selectionStart === 0 && this.textarea.selectionEnd === 0 && this.textarea.value.length > 0) {
                    e.stopPropagation();
                }
            }
            if (e.key === 'Tab') {
                e.preventDefault();
                const start = this.textarea.selectionStart;
                const end = this.textarea.selectionEnd;
                this.textarea.value = this.textarea.value.substring(0, start) + "\t" + this.textarea.value.substring(end);
                this.textarea.selectionStart = this.textarea.selectionEnd = start + 1;
            }
        });

        this.wrapper.appendChild(this.textarea);

        setTimeout(() => {
            if (this.textarea.scrollHeight > 0 && !CSS.supports('field-sizing', 'content')) {
                this.textarea.style.height = (this.textarea.scrollHeight) + 'px';
            }
        }, 0);

        return this.wrapper;
    }

    save(blockContent) {
        const textarea = blockContent.querySelector('textarea');
        return {
            html: textarea.value
        };
    }

    validate(savedData) {
        if (!savedData.html || savedData.html.trim() === '') {
            return false;
        }
        return true;
    }
}
