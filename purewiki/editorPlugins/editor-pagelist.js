/**
 * PureWiki - Page List Tool
 *
 * Dynamic list of pages based on criteria.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

class PageListTool {
    static get toolbox() {
        return {
            title: typeof __ === 'function' ? __('plugins.page_list') : 'Page List',
            icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" fill-rule="evenodd" d="M20 4H4a1 1 0 0 0-1 1v14a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1V5a1 1 0 0 0-1-1M4 2a3 3 0 0 0-3 3v14a3 3 0 0 0 3 3h16a3 3 0 0 0 3-3V5a3 3 0 0 0-3-3zm2 5h2v2H6zm5 0a1 1 0 1 0 0 2h6a1 1 0 1 0 0-2zm-3 4H6v2h2zm2 1a1 1 0 0 1 1-1h6a1 1 0 1 1 0 2h-6a1 1 0 0 1-1-1m-2 3H6v2h2zm2 1a1 1 0 0 1 1-1h6a1 1 0 1 1 0 2h-6a1 1 0 0 1-1-1" clip-rule="evenodd"/></svg>'
        };
    }

    constructor({ data }) {
        this.data = {
            startPath: data.startPath || '/',
            boldHeader: data.boldHeader || false,
            style: data.style === 'cards' ? 'cards' : 'list',
            sort: data.sort || 'date_desc',
            tag: data.tag || '',
            columns: data.columns || 2,
            perPage: data.perPage ?? 6,
            paginate: data.paginate ?? true,
            showImage: data.showImage ?? true,
            showDate: data.showDate ?? true,
            showTeaser: data.showTeaser ?? true,
            showButton: data.showButton ?? true
        };
        this.wrapper = undefined;
    }

    render() {
        this.wrapper = document.createElement('div');
        this.wrapper.classList.add('pw-pagelist-plugin-wrapper');

        const checkbox = ([name, labelKey, fallback]) => `
            <label class="pw-pagelist-plugin-check">
                <input type="checkbox" class="pw-checkbox" data-option="${name}">
                <span>${this._t(labelKey, fallback)}</span>
            </label>`;
        const options = (values) => values
            .map(([value, labelKey, fallback]) => `<option value="${value}">${this._t(labelKey, fallback)}</option>`).join('');

        this.wrapper.innerHTML = `
            <strong class="pw-pagelist-plugin-title">${this._t('plugins.page_list_desc', 'Page List')}</strong>
            <div class="pw-pagelist-plugin-options">
                <div>
                    <label class="pw-pagelist-plugin-label">${this._t('plugins.page_list_start_path', 'Start Path (e.g. / or /docs):')}</label>
                    <input class="pw-input" data-field="startPath" placeholder="/">
                </div>
                <div>
                    <label class="pw-pagelist-plugin-label">${this._t('plugins.page_list_style', 'Display')}</label>
                    <select class="pw-input" data-field="style">${options([
                        ['list', 'plugins.page_list_style_list', 'Tree'],
                        ['cards', 'plugins.page_list_style_cards', 'Cards']
                    ])}</select>
                </div>
            </div>
            <div class="pw-pagelist-plugin-list-options">
                ${checkbox(['boldHeader', 'plugins.page_list_bold', 'Bold Headings'])}
            </div>
            <div class="pw-pagelist-plugin-card-options">
                <div class="pw-pagelist-plugin-options">
                    <div>
                        <label class="pw-pagelist-plugin-label">${this._t('plugins.page_list_sort', 'Sort by')}</label>
                        <select class="pw-input" data-field="sort">${options([
                            ['date_desc', 'plugins.page_list_sort_date_desc', 'Newest first'],
                            ['date_asc', 'plugins.page_list_sort_date_asc', 'Oldest first'],
                            ['title', 'plugins.page_list_sort_title', 'Title'],
                            ['order', 'plugins.page_list_sort_order', 'Treeview order']
                        ])}</select>
                    </div>
                    <div>
                        <label class="pw-pagelist-plugin-label">${this._t('plugins.page_list_tag', 'Only pages with tag')}</label>
                        <input class="pw-input" data-field="tag">
                    </div>
                    <div>
                        <label class="pw-pagelist-plugin-label">${this._t('plugins.page_list_columns', 'Columns')}</label>
                        <select class="pw-input" data-field="columns">${options([[1, '', '1'], [2, '', '2'], [3, '', '3'], [4, '', '4']])}</select>
                    </div>
                    <div>
                        <label class="pw-pagelist-plugin-label">${this._t('plugins.page_list_per_page', 'Cards per page (0 = all)')}</label>
                        <input type="number" min="0" class="pw-input" data-field="perPage">
                    </div>
                </div>
                <div class="pw-pagelist-plugin-checks">
                    ${[
                        ['paginate', 'plugins.page_list_paginate', 'Pagination'],
                        ['showImage', 'plugins.page_list_show_image', 'Image'],
                        ['showDate', 'plugins.page_list_show_date', 'Date'],
                        ['showTeaser', 'plugins.page_list_show_teaser', 'Teaser'],
                        ['showButton', 'plugins.page_list_show_button', 'Button']
                    ].map(checkbox).join('')}
                </div>
            </div>`;

        // Set values after building the markup
        this.wrapper.querySelectorAll('[data-field]').forEach(input => {
            input.value = this.data[input.dataset.field];
            input.addEventListener('input', () => this._update());
            input.addEventListener('change', () => this._update());
        });
        this.wrapper.querySelectorAll('[data-option]').forEach(box => {
            box.checked = !!this.data[box.dataset.option];
            box.addEventListener('change', () => this._update());
        });
        this._syncVisibility();

        return this.wrapper;
    }

    save() {
        const data = {
            startPath: this.data.startPath.trim() || '/',
            boldHeader: this.data.boldHeader,
            style: this.data.style
        };
        if (this.data.style !== 'cards') return data;

        return Object.assign(data, {
            sort: this.data.sort,
            tag: this.data.tag.trim(),
            columns: Math.min(4, Math.max(1, parseInt(this.data.columns, 10) || 2)),
            perPage: Math.max(0, parseInt(this.data.perPage, 10) || 0),
            paginate: this.data.paginate,
            showImage: this.data.showImage,
            showDate: this.data.showDate,
            showTeaser: this.data.showTeaser,
            showButton: this.data.showButton
        });
    }

    /** Reads all inputs into this.data */
    _update() {
        this.wrapper.querySelectorAll('[data-field]').forEach(input => this.data[input.dataset.field] = input.value);
        this.wrapper.querySelectorAll('[data-option]').forEach(box => this.data[box.dataset.option] = box.checked);
        this._syncVisibility();
        this._markChanged();
    }

    /** Shows only the options of the selected display */
    _syncVisibility() {
        const isCards = this.data.style === 'cards';
        this.wrapper.querySelector('.pw-pagelist-plugin-list-options').hidden = isCards;
        this.wrapper.querySelector('.pw-pagelist-plugin-card-options').hidden = !isCards;
    }

    _t(key, fallback) {
        return key && typeof __ === 'function' ? __(key) : fallback;
    }

    _markChanged() {
        if (window.pwEditorInitializing) return;
        if (typeof hasUnsavedChanges !== 'undefined') hasUnsavedChanges = true;
        if (typeof autoSaveTimeout !== 'undefined') clearTimeout(autoSaveTimeout);
        if (typeof saveCurrentDraft === 'function') {
            autoSaveTimeout = setTimeout(() => saveCurrentDraft(true), 1000);
        }
    }
}
