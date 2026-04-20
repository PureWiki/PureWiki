/**
 * PureWiki - Table of Contents
 *
 * Automatically lists all headings of the current page.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

class TableOfContentsTool {
    static get toolbox() {
        return {
            title: typeof __ === 'function' ? __('plugins.table_of_contents') : 'Table of Contents',
            icon: `<svg width="17" height="15" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M3 5H21M3 10H21M3 15H21M3 20H21" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M7 5V20" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>`
        };
    }

    constructor({ data, config, api }) {
        this.api = api;
        this.data = {
            startLevel: data.startLevel || 1,
            endLevel: data.endLevel || 6
        };
        this.wrapper = undefined;
    }

    render() {
        this.wrapper = document.createElement('div');
        this.wrapper.classList.add('pw-toc-plugin-wrapper');

        const title = document.createElement('div');
        title.innerHTML = '<strong>' + (typeof __ === 'function' ? __('plugins.toc_title') : 'Table of Contents') + '</strong>';
        title.style.color = 'var(--pw-text)';
        title.style.marginBottom = '12px';
        title.style.fontSize = '1.1em';
        this.wrapper.appendChild(title);

        const configGrid = document.createElement('div');
        configGrid.style.display = 'grid';
        configGrid.style.gridTemplateColumns = '1fr 1fr';
        configGrid.style.gap = '16px';

        const startWrapper = document.createElement('div');
        const startLabel = document.createElement('label');
        startLabel.innerText = typeof __ === 'function' ? __('plugins.toc_start_level') : 'Start Level (H1-H6):';
        startLabel.style.display = 'block';
        startLabel.style.fontSize = '0.85em';
        startLabel.style.color = 'var(--pw-text-muted)';
        startLabel.style.marginBottom = '6px';

        this.startInput = document.createElement('input');
        this.startInput.type = 'number';
        this.startInput.min = '1';
        this.startInput.max = '6';
        this.startInput.value = this.data.startLevel;
        this.startInput.classList.add('pw-input');

        startWrapper.appendChild(startLabel);
        startWrapper.appendChild(this.startInput);

        const endWrapper = document.createElement('div');
        const endLabel = document.createElement('label');
        endLabel.innerText = typeof __ === 'function' ? __('plugins.toc_end_level') : 'End Level (H1-H6):';
        endLabel.style.display = 'block';
        endLabel.style.fontSize = '0.85em';
        endLabel.style.color = 'var(--pw-text-muted)';
        endLabel.style.marginBottom = '6px';

        this.endInput = document.createElement('input');
        this.endInput.type = 'number';
        this.endInput.min = '1';
        this.endInput.max = '6';
        this.endInput.value = this.data.endLevel;
        this.endInput.classList.add('pw-input');

        endWrapper.appendChild(endLabel);
        endWrapper.appendChild(this.endInput);

        configGrid.appendChild(startWrapper);
        configGrid.appendChild(endWrapper);
        this.wrapper.appendChild(configGrid);

        const previewTitle = document.createElement('div');
        previewTitle.innerHTML = '<em>' + (typeof __ === 'function' ? __('plugins.toc_preview') : 'In-Editor Preview:') + '</em>';
        previewTitle.style.fontSize = '0.8em';
        previewTitle.style.color = 'var(--pw-text-muted)';
        previewTitle.style.marginTop = '16px';
        previewTitle.style.marginBottom = '6px';
        this.wrapper.appendChild(previewTitle);

        this.previewContainer = document.createElement('div');
        this.previewContainer.classList.add('pw-toc-preview-container');
        this.wrapper.appendChild(this.previewContainer);

        const updatePreview = () => this._updatePreview();
        this.startInput.addEventListener('input', updatePreview);
        this.endInput.addEventListener('input', updatePreview);

        return this.wrapper;
    }

    rendered() {
        if (window.pwEditorInitializing) return;
        this._updatePreview();
    }

    async _updatePreview() {
        if (!this.previewContainer) return;

        const headings = await this._getHeadings();
        this.previewContainer.innerHTML = '';

        if (headings.length === 0) {
            this.previewContainer.innerHTML = '<span style="color:var(--pw-text-muted); font-style:italic;">' + (typeof __ === 'function' ? __('plugins.toc_no_headings') : 'No headings found for the selected range.') + '</span>';
            return;
        }

        headings.forEach(h => {
            const item = document.createElement('div');
            item.classList.add('pw-toc-preview-item');
            item.style.paddingLeft = `${(h.level - 1) * 16}px`;

            item.innerHTML = `
                <span class="pw-toc-preview-level">H${h.level}</span>
                <span class="pw-toc-preview-text">${h.text}</span>
            `;
            this.previewContainer.appendChild(item);
        });

        const note = document.createElement('div');
        note.style.cssText = 'font-size:0.75em; color:var(--pw-text-muted); margin-top:10px; font-style:italic;';
        note.textContent = typeof __ === 'function'
            ? __('plugins.toc_md_hint')
            : 'Markdown headings may not appear in this preview.';
        this.previewContainer.appendChild(note);
    }

    async _getHeadings() {
        try {
            const start = parseInt(this.startInput.value) || 1;
            const end = parseInt(this.endInput.value) || 6;

            const redactor = document.querySelector('.codex-editor__redactor');
            if (!redactor) return [];

            const headerElements = redactor.querySelectorAll('.ce-header');
            const headings = [];

            headerElements.forEach(el => {
                const tagName = el.tagName.toUpperCase();
                if (tagName.startsWith('H')) {
                    const level = parseInt(tagName.replace('H', ''));
                    if (level >= start && level <= end) {
                        const text = el.innerText || el.textContent;
                        if (text.trim()) {
                            headings.push({
                                text: text.trim(),
                                level: level
                            });
                        }
                    }
                }
            });

            return headings;
        } catch (e) {
            if (typeof window.notify === 'function') {
                window.notify(typeof __ === 'function' ? __('plugins.toc_error_extract') : 'TOC Plugin: Failed to extract headings', 'error');
            }
            return [];
        }
    }

    save(blockContent) {
        return {
            startLevel: parseInt(this.startInput.value) || 1,
            endLevel: parseInt(this.endInput.value) || 6
        };
    }
}
