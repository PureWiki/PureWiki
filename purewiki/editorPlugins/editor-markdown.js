/**
 * PureWiki - Markdown Tool
 *
 * Markdown import/export tool for Editor.js.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

class MarkdownTool {
    static get isReadOnlySupported() {
        return true;
    }

    static get enableLineBreaks() {
        return true;
    }

    static get toolbox() {
        return {
            title: typeof __ === 'function' ? __('plugins.markdown') : 'Markdown',
            icon: `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M20.56 18H3.44C2.65 18 2 17.37 2 16.59V7.41C2 6.63 2.65 6 3.44 6h17.12c.79 0 1.44.63 1.44 1.41v9.18c0 .78-.65 1.41-1.44 1.41M6.81 15.19v-3.66l1.92 2.35l1.92-2.35v3.66h1.93V8.81h-1.93l-1.92 2.35l-1.92-2.35H4.89v6.38zM19.69 12h-1.92V8.81h-1.92V12h-1.93l2.89 3.28z"/></svg>`
        };
    }

    constructor({ data, config, api, readOnly }) {
        this.api = api;
        this.readOnly = readOnly;
        this.data = {
            markdown: data.markdown || ''
        };

        this.wrapper = undefined;
        this.textarea = undefined;
    }

    render() {
        this.wrapper = document.createElement('div');
        this.wrapper.classList.add('pw-editor-markdown-wrapper');
        this.title = document.createElement('div');
        this.title.classList.add('pw-editor-markdown-title');
        this.title.textContent = typeof __ === 'function' ? __('plugins.markdown_title') : 'Markdown:';
        this.wrapper.appendChild(this.title);
        this.textarea = document.createElement('textarea');
        this.textarea.classList.add('pw-editor-markdown-textarea');
        this.textarea.placeholder = typeof __ === 'function' ? __('plugins.markdown_placeholder') : 'Enter Markdown...';
        this.textarea.value = this.data.markdown;

        if (this.readOnly) {
            this.textarea.disabled = true;
        }

        this.textarea.addEventListener('input', () => {
            // Modern browsers use field-sizing: content from CSS.
            // Fallback for others:
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
                // If we are at the beginning and have text, stop Editor.js from jumping blocks
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
            if (this.textarea.scrollHeight > 0) {
                this.textarea.style.height = (this.textarea.scrollHeight) + 'px';
            }
        }, 0);

        return this.wrapper;
    }

    save(blockContent) {
        const textarea = blockContent.querySelector('textarea');
        return {
            markdown: textarea.value
        };
    }

    validate(savedData) {
        if (!savedData.markdown || savedData.markdown.trim() === '') {
            return false;
        }
        return true;
    }
}
