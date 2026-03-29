/**
 * PureWiki - Code Prism Tool
 *
 * Code highlighting tool for Editor.js using Prism.js.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */
class CodePrism {
    static get toolkit() {
        return 'code';
    }

    static get isReadOnlySupported() {
        return true;
    }

    constructor({ data, config, api, readOnly }) {
        this.api = api;
        this.readOnly = readOnly;
        this.data = {
            code: data.code || '',
            language: data.language || 'none'
        };

        this.nodes = {
            holder: null,
            textarea: null,
            select: null
        };

        this.languages = [
            { label: 'None / Plain', value: 'none' },
            { label: 'C++', value: 'cpp' },
            { label: 'C#', value: 'csharp' },
            { label: 'CSS', value: 'css' },
            { label: 'HTML / XML', value: 'markup' },
            { label: 'Java', value: 'java' },
            { label: 'JavaScript', value: 'javascript' },
            { label: 'JSON', value: 'json' },
            { label: 'Markdown', value: 'markdown' },
            { label: 'PHP', value: 'php' },
            { label: 'Python', value: 'python' },
            { label: 'Shell / Bash', value: 'bash' },
            { label: 'SQL', value: 'sql' },
            { label: 'TypeScript', value: 'typescript' },
            { label: 'YAML', value: 'yaml' }
        ];
    }

    render() {
        const holder = document.createElement('div');
        holder.classList.add('pw-editor-code-prism');
        holder.style.cssText = 'background: #1e1f22; border: 1px solid #444; border-radius: 4px; padding: 10px; display: flex; flex-direction: column; gap: 8px; position: relative;';

        const toolbox = document.createElement('div');
        toolbox.style.cssText = 'position: absolute; right: 10px; top: 10px; opacity: 0.5; font-size: 0.8rem; pointer-events: none; color: #aaa;';
        toolbox.innerHTML = '<iconify-icon icon="mdi:code-braces"></iconify-icon> CODE';
        holder.appendChild(toolbox);

        const select = document.createElement('select');
        select.style.cssText = 'width: 150px; background: #121212; color: #fff; border: 1px solid #444; border-radius: 4px; padding: 4px 8px; font-size: 12px; cursor: pointer; outline: none;';

        this.languages.forEach(lang => {
            const opt = document.createElement('option');
            opt.value = lang.value;
            opt.textContent = lang.label;
            if (this.data.language === lang.value) opt.selected = true;
            select.appendChild(opt);
        });

        select.addEventListener('change', (e) => {
            this.data.language = e.target.value;
        });

        holder.appendChild(select);

        const textarea = document.createElement('textarea');
        textarea.classList.add('pw-editor-code-prism-textarea');
        textarea.placeholder = typeof __ === 'function' ? __('plugins.code_placeholder') : 'Paste or write code here...';
        textarea.value = this.data.code;

        if (this.readOnly) {
            textarea.readOnly = true;
        }

        textarea.addEventListener('input', (e) => {
            this.data.code = e.target.value;
            if (!CSS.supports('field-sizing', 'content')) {
                textarea.style.height = 'auto';
                textarea.style.height = textarea.scrollHeight + 'px';
            }
        });

        textarea.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.stopPropagation();
            }
            if (e.key === 'Backspace') {
                if (textarea.selectionStart === 0 && textarea.selectionEnd === 0 && textarea.value.length > 0) {
                    e.stopPropagation();
                }
            }
            if (e.key === 'Tab') {
                e.preventDefault();
                const start = textarea.selectionStart;
                const end = textarea.selectionEnd;
                textarea.value = textarea.value.substring(0, start) + "\t" + textarea.value.substring(end);
                textarea.selectionStart = textarea.selectionEnd = start + 1;
            }
        });

        holder.appendChild(textarea);

        this.nodes.holder = holder;
        this.nodes.textarea = textarea;
        this.nodes.select = select;

        setTimeout(() => {
            textarea.style.height = 'auto';
            textarea.style.height = textarea.scrollHeight + 'px';
        }, 0);

        return holder;
    }

    save() {
        return {
            code: this.data.code,
            language: this.data.language
        };
    }

    static get toolbox() {
        return {
            title: typeof __ === 'function' ? __('plugins.code') : 'Code',
            icon: '<svg width="20" height="20" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M5.9 5.9L1.8 10l4.1 4.1 4.1-4.1-4.1-4.1zM14.1 5.9L10 10l4.1 4.1 4.1-4.1-4.1-4.1z"/></svg>'
        };
    }
}
