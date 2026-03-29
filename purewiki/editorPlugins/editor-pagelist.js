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

    constructor({ data, config, api }) {
        this.api = api;
        this.data = {
            startPath: data.startPath || '/',
            boldHeader: data.boldHeader || false
        };
        this.wrapper = undefined;
    }

    render() {
        this.wrapper = document.createElement('div');
        this.wrapper.classList.add('pw-pagelist-plugin-wrapper');
        this.wrapper.style.padding = '12px';
        this.wrapper.style.border = '1px solid var(--pw-border)';
        this.wrapper.style.borderRadius = '6px';
        this.wrapper.style.backgroundColor = 'var(--pw-bg-panel)';

        const title = document.createElement('div');
        title.innerHTML = '<strong>' + (typeof __ === 'function' ? __('plugins.page_list_desc') : 'Page List (Treeview)') + '</strong>';
        title.style.marginBottom = '12px';
        title.style.color = 'var(--pw-text)';
        this.wrapper.appendChild(title);

        const pathLabel = document.createElement('label');
        pathLabel.innerText = typeof __ === 'function' ? __('plugins.page_list_start_path') : 'Start Path (e.g. / or /docs):';
        pathLabel.style.display = 'block';
        pathLabel.style.fontSize = '0.85em';
        pathLabel.style.color = 'var(--pw-text-muted)';
        pathLabel.style.marginBottom = '4px';

        this.pathInput = document.createElement('input');
        this.pathInput.classList.add('pw-input');
        this.pathInput.value = this.data.startPath;
        this.pathInput.placeholder = '/';
        this.pathInput.style.width = '100%';
        this.pathInput.style.marginBottom = '12px';

        this.wrapper.appendChild(pathLabel);
        this.wrapper.appendChild(this.pathInput);

        const boldWrapper = document.createElement('label');
        boldWrapper.style.display = 'flex';
        boldWrapper.style.alignItems = 'center';
        boldWrapper.style.gap = '8px';
        boldWrapper.style.cursor = 'pointer';
        boldWrapper.style.fontSize = '0.9em';
        boldWrapper.style.color = 'var(--pw-text)';

        this.boldCheckbox = document.createElement('input');
        this.boldCheckbox.type = 'checkbox';
        this.boldCheckbox.classList.add('pw-checkbox');
        this.boldCheckbox.checked = this.data.boldHeader;

        boldWrapper.appendChild(this.boldCheckbox);
        boldWrapper.appendChild(document.createTextNode(' ' + (typeof __ === 'function' ? __('plugins.page_list_bold') : 'Bold Headings')));

        this.wrapper.appendChild(boldWrapper);

        return this.wrapper;
    }

    save(blockContent) {
        return {
            startPath: this.pathInput.value.trim() || '/',
            boldHeader: this.boldCheckbox.checked
        };
    }
}
