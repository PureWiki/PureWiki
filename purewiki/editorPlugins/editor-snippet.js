/**
 * PureWiki - Snippet Tool
 *
 * Editor.js Plugin for selecting and embedding Snippets.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */
class SnippetTool {
    static get toolbox() {
        return {
            title: 'Snippet',
            icon: '<iconify-icon icon="mdi:code-tags"></iconify-icon>'
        };
    }

    constructor({ data, api, readOnly }) {
        this.api = api;
        this.data = {
            snippetName: data.snippetName || ''
        };
        this.readOnly = readOnly;
        this.wrapper = undefined;
        this.select = undefined;
    }

    render() {
        this.wrapper = document.createElement('div');
        this.wrapper.classList.add('pw-snippet-tool-wrapper');
        this.wrapper.style.padding = '10px';
        this.wrapper.style.border = '1px dashed var(--pw-border)';
        this.wrapper.style.borderRadius = '4px';
        this.wrapper.style.backgroundColor = 'var(--pw-bg-light)';

        this.wrapper.innerHTML = `
            <div style="margin-bottom: 10px; font-weight: bold; color: var(--pw-text-muted);">
                <iconify-icon icon="mdi:code-tags"></iconify-icon> Select a Snippet to embed:
            </div>
            <select class="pw-input" style="width: 100%;">
                <option value="">-- Select a Snippet --</option>
            </select>
        `;

        this.select = this.wrapper.querySelector('select');

        if (this.readOnly) {
            this.select.disabled = true;
        }

        const fetchSnippets = async () => {
            try {
                const result = await apiCall('list_snippets');
                if (result.success && result.snippets) {
                    result.snippets.forEach(snippet => {
                        const opt = document.createElement('option');
                        opt.value = snippet.folder;
                        opt.text = snippet.name;
                        if (snippet.folder === this.data.snippetName) {
                            opt.selected = true;
                        }
                        this.select.appendChild(opt);
                    });
                }
            } catch (e) {
                if (typeof window.notify === 'function') {
                    window.notify('Failed to load snippets', 'error');
                }
            }
        };

        fetchSnippets();

        this.select.addEventListener('change', () => {
            this.data.snippetName = this.select.value;
        });

        return this.wrapper;
    }

    save(blockContent) {
        return {
            snippetName: this.select.value
        };
    }

    static get isReadOnlySupported() {
        return true;
    }
}

window.SnippetTool = SnippetTool;