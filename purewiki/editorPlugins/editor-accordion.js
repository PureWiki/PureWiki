/**
 * PureWiki - Accordion Tool
 *
 * Accordion block tool for Editor.js — collapsible sections with nested Editor.js content.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */
class AccordionTool {
    static get isReadOnlySupported() {
        return true;
    }

    static get toolbox() {
        return {
            title: typeof __ === 'function' ? __('plugins.accordion') : 'Accordion',
            icon: '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M3 5v2h18V5zm0 6v2h18v-2zm0 6v2h18v-2z"/></svg>'
        };
    }

    constructor({ data, api, readOnly }) {
        this.api = api;
        this.readOnly = readOnly;
        this.data = {
            items: (data.items && data.items.length > 0)
                ? data.items
                : [{ title: '', defaultOpen: true, blocks: [] }]
        };
        this.wrapper = null;
        this._nestedEditors = [];
    }

    render() {
        this.wrapper = document.createElement('div');
        this.wrapper.classList.add('pw-editor-accordion');

        this.data.items.forEach((item, index) => {
            this._renderItem(item, index);
        });

        if (!this.readOnly) {
            const addBtn = document.createElement('button');
            addBtn.className = 'pw-btn pw-editor-accordion-add';
            addBtn.type = 'button';
            addBtn.innerHTML = '<iconify-icon icon="mdi:plus"></iconify-icon> ' + (typeof __ === 'function' ? __('plugins.accordion_add') : 'Add Item');
            addBtn.addEventListener('click', () => {
                const newItem = { title: '', defaultOpen: false, blocks: [] };
                this.data.items.push(newItem);
                this._renderItem(newItem, this.data.items.length - 1, addBtn);
            });
            this.wrapper.appendChild(addBtn);
        }

        return this.wrapper;
    }

    _renderItem(item, index, insertBefore) {
        const container = document.createElement('div');
        container.classList.add('pw-editor-accordion-item');
        if (item.defaultOpen) container.classList.add('pw-open');

        const header = document.createElement('div');
        header.classList.add('pw-editor-accordion-header');

        const toggleIcon = document.createElement('iconify-icon');
        toggleIcon.setAttribute('icon', 'mdi:chevron-right');
        toggleIcon.classList.add('pw-editor-accordion-chevron');

        const titleInput = document.createElement('input');
        titleInput.type = 'text';
        titleInput.placeholder = typeof __ === 'function' ? __('plugins.accordion_title_placeholder') : 'Accordion title…';
        titleInput.value = item.title || '';
        titleInput.classList.add('pw-editor-accordion-title');
        titleInput.readOnly = this.readOnly;
        titleInput.addEventListener('input', () => {
            item.title = titleInput.value;
        });
        titleInput.addEventListener('click', (e) => e.stopPropagation());
        titleInput.addEventListener('keydown', (e) => e.stopPropagation());

        const controls = document.createElement('div');
        controls.classList.add('pw-editor-accordion-controls');

        if (!this.readOnly) {
            const openLabel = document.createElement('label');
            openLabel.classList.add('pw-editor-accordion-opt');
            const openCheck = document.createElement('input');
            openCheck.type = 'checkbox';
            openCheck.checked = item.defaultOpen;
            openCheck.classList.add('pw-checkbox');
            openCheck.addEventListener('change', () => {
                item.defaultOpen = openCheck.checked;
            });
            openCheck.addEventListener('click', (e) => e.stopPropagation());
            openLabel.appendChild(openCheck);
            openLabel.appendChild(document.createTextNode(' ' + (typeof __ === 'function' ? __('plugins.accordion_open') : 'Open')));
            openLabel.addEventListener('click', (e) => e.stopPropagation());
            controls.appendChild(openLabel);

            const delBtn = document.createElement('button');
            delBtn.type = 'button';
            delBtn.className = 'pw-editor-accordion-del';
            delBtn.innerHTML = '<iconify-icon icon="mdi:delete-outline"></iconify-icon>';
            delBtn.title = typeof __ === 'function' ? __('plugins.accordion_remove') : 'Remove item';
            delBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                if (this.data.items.length <= 1) return;
                const idx = this.data.items.indexOf(item);
                if (idx > -1) {
                    const editor = this._nestedEditors[idx];
                    if (editor) {
                        try { editor.destroy(); } catch (_) {}
                    }
                    this._nestedEditors.splice(idx, 1);
                    this.data.items.splice(idx, 1);
                    container.remove();
                }
            });
            controls.appendChild(delBtn);
        }

        header.appendChild(toggleIcon);
        header.appendChild(titleInput);
        header.appendChild(controls);

        header.addEventListener('click', () => {
            container.classList.toggle('pw-open');
        });

        const body = document.createElement('div');
        body.classList.add('pw-editor-accordion-body');

        const editorHolder = document.createElement('div');
        const holderId = 'pw-acc-editor-' + Date.now() + '-' + Math.random().toString(36).substr(2, 6);
        editorHolder.id = holderId;
        editorHolder.classList.add('pw-editor-accordion-editor', 'pw-editor-nested');
        body.appendChild(editorHolder);

        body.addEventListener('click', (e) => e.stopPropagation());
        body.addEventListener('keydown', (e) => e.stopPropagation());
        body.addEventListener('mousedown', (e) => e.stopPropagation());

        container.appendChild(header);
        container.appendChild(body);

        if (insertBefore) {
            this.wrapper.insertBefore(container, insertBefore);
        } else {
            this.wrapper.appendChild(container);
        }

        this._initNestedEditor(holderId, item, index);
    }

    _initNestedEditor(holderId, item, index) {
        const tools = window.PW_EDITOR_TOOLS || {
            header: { class: Header, config: { levels: [2, 3, 4, 5, 6], defaultLevel: 3 } },
            list: { class: EditorjsList, inlineToolbar: true },
            delimiter: Delimiter
        };

        const editorInstance = new EditorJS({
            holder: holderId,
            data: { blocks: item.blocks || [] },
            placeholder: typeof __ === 'function' ? __('plugins.accordion_content_placeholder') : 'Accordion content…',
            minHeight: 0,
            i18n: window.PW_EDITOR_I18N_CONFIG || undefined,
            tools: tools,
            readOnly: this.readOnly,
            onChange: () => {
                if (window.pwEditorInitializing) return;
                if (typeof hasUnsavedChanges !== 'undefined') hasUnsavedChanges = true;
                if (typeof autoSaveTimeout !== 'undefined') clearTimeout(autoSaveTimeout);
                if (typeof saveCurrentDraft === 'function') {
                    autoSaveTimeout = setTimeout(() => saveCurrentDraft(true), 3000);
                }
            }
        });

        this._nestedEditors[index] = editorInstance;
    }

    async save() {
        const items = [];
        for (let i = 0; i < this.data.items.length; i++) {
            const item = this.data.items[i];
            let blocks = [];
            const editor = this._nestedEditors[i];
            if (editor) {
                try {
                    const output = await editor.save();
                    blocks = output.blocks || [];
                } catch (_) {
                    blocks = item.blocks || [];
                }
            }
            items.push({
                title: item.title || '',
                defaultOpen: !!item.defaultOpen,
                blocks: blocks
            });
        }
        return { items };
    }

    destroy() {
        this._nestedEditors.forEach(editor => {
            if (editor) {
                try { editor.destroy(); } catch (_) {}
            }
        });
        this._nestedEditors = [];
    }
}
