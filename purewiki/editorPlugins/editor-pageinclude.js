/**
 * PureWiki - Page Include Tool
 *
 * Embed content from other wiki pages.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

class PageIncludeTool {
    static get toolbox() {
        return {
            title: typeof __ === 'function' ? __('plugins.page_include') : 'Page Include',
            icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8zm4 18H6V4h7v5h5zM8 15.01V18h3v-2.99zM8 12v1.01h8V12z"/></svg>'
        };
    }

    constructor({ data, config, api }) {
        this.api = api;
        this.data = {
            pagePath: data.pagePath || ''
        };
        this.wrapper = undefined;
    }

    render() {
        this.wrapper = document.createElement('div');
        this.wrapper.classList.add('pw-settings-panel');
        this.wrapper.style.padding = '16px';
        this.wrapper.style.marginBottom = '10px';

        const header = document.createElement('div');
        header.classList.add('pw-settings-heading');
        header.style.display = 'flex';
        header.style.alignItems = 'center';
        header.style.gap = '8px';
        header.innerHTML = '<iconify-icon icon="mdi:file-import-outline"></iconify-icon> <strong>' + (typeof __ === 'function' ? __('plugins.page_include') : 'Page Include') + '</strong>';
        this.wrapper.appendChild(header);

        const hint = document.createElement('p');
        hint.classList.add('pw-hint-compact');
        hint.innerText = typeof __ === 'function' ? __('plugins.page_include_hint') : 'Path of the page to include:';
        this.wrapper.appendChild(hint);

        const inputWrapper = document.createElement('div');
        inputWrapper.style.position = 'relative';
        inputWrapper.style.display = 'flex';
        inputWrapper.style.alignItems = 'center';

        this.pathInput = document.createElement('input');
        this.pathInput.classList.add('pw-input');
        this.pathInput.value = this.data.pagePath;
        this.pathInput.placeholder = '/path/to/page';
        this.pathInput.style.width = '100%';
        this.pathInput.style.paddingRight = '35px';
        this.pathInput.addEventListener('input', () => {
            if (window.pwEditorInitializing) return;
        });

        this.statusIcon = document.createElement('div');
        this.statusIcon.style.position = 'absolute';
        this.statusIcon.style.right = '10px';
        this.statusIcon.style.display = 'flex';
        this.statusIcon.style.alignItems = 'center';
        this.statusIcon.innerHTML = '<iconify-icon icon="mdi:help-circle-outline" style="color:var(--pw-text-muted);"></iconify-icon>';

        inputWrapper.appendChild(this.pathInput);
        inputWrapper.appendChild(this.statusIcon);
        this.wrapper.appendChild(inputWrapper);

        new PagePicker(this.pathInput, {
            onValidate: (exists) => {
                if (exists) {
                    this.statusIcon.innerHTML = '<iconify-icon icon="mdi:check-circle" style="color:#28a745;"></iconify-icon>';
                } else {
                    const val = this.pathInput.value.trim();
                    if (val === '') {
                        this.statusIcon.innerHTML = '<iconify-icon icon="mdi:help-circle-outline" style="color:var(--pw-text-muted);"></iconify-icon>';
                    } else {
                        this.statusIcon.innerHTML = '<iconify-icon icon="mdi:alert-circle" style="color:var(--pw-danger);"></iconify-icon>';
                    }
                }
            }
        });

        const info = document.createElement('div');
        info.classList.add('pw-hint-small');
        info.style.marginTop = '8px';
        info.innerText = typeof __ === 'function' ? __('plugins.page_include_info') : 'Content updates dynamically during rendering.';
        this.wrapper.appendChild(info);

        return this.wrapper;
    }

    save(blockContent) {
        return {
            pagePath: this.pathInput.value.trim()
        };
    }

    static get sanitize() {
        return {
            pagePath: false
        };
    }
}
window.PageIncludeTool = PageIncludeTool;
