/**
 * PureWiki - Grid Tool
 *
 * Layout grid tool for Editor.js — responsive grid layout with nested Editor.js content per cell.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */
class GridTool {
    static get isReadOnlySupported() {
        return true;
    }

    static get toolbox() {
        return {
            title: typeof __ === 'function' ? __('plugins.grid') : 'Grid',
            icon: '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M3 3h8v8H3zm0 10h8v8H3zm10-10h8v8h-8zm0 10h8v8h-8z"/></svg>'
        };
    }

    constructor({ data, api, readOnly }) {
        this.api = api;
        this.readOnly = readOnly;
        this.data = {
            columns: data.columns || 2,
            minWidth: data.minWidth || 200,
            cells: (data.cells && data.cells.length > 0)
                ? data.cells
                : [{ blocks: [] }, { blocks: [] }]
        };
        this.wrapper = null;
        this.gridContainer = null;
        this._nestedEditors = [];
    }

    render() {
        this.wrapper = document.createElement('div');
        this.wrapper.classList.add('pw-editor-grid');

        if (!this.readOnly) {
            const toolbar = document.createElement('div');
            toolbar.classList.add('pw-editor-grid-toolbar');

            const colLabel = document.createElement('span');
            colLabel.textContent = typeof __ === 'function' ? __('plugins.grid_columns') : 'Columns:';
            colLabel.classList.add('pw-editor-grid-label', 'pw-editor-toolbar-label');

            const colSelect = document.createElement('select');
            colSelect.classList.add('pw-editor-grid-select', 'pw-editor-ctrl');
            for (let i = 2; i <= 6; i++) {
                const opt = document.createElement('option');
                opt.value = i;
                opt.textContent = i;
                if (i === this.data.columns) opt.selected = true;
                colSelect.appendChild(opt);
            }
            colSelect.addEventListener('change', () => {
                this.data.columns = parseInt(colSelect.value, 10);
                this._updateGridStyle();
            });

            const minLabel = document.createElement('span');
            minLabel.textContent = typeof __ === 'function' ? __('plugins.grid_min_width') : 'Min width:';
            minLabel.classList.add('pw-editor-grid-label', 'pw-editor-toolbar-label');

            const minInput = document.createElement('input');
            minInput.type = 'number';
            minInput.min = 100;
            minInput.max = 800;
            minInput.step = 10;
            minInput.value = this.data.minWidth;
            minInput.classList.add('pw-editor-grid-input', 'pw-editor-ctrl');
            minInput.addEventListener('input', () => {
                this.data.minWidth = parseInt(minInput.value, 10) || 200;
                this._updateGridStyle();
            });

            const pxLabel = document.createElement('span');
            pxLabel.textContent = 'px';
            pxLabel.classList.add('pw-editor-grid-label', 'pw-editor-toolbar-label');

            const addBtn = document.createElement('button');
            addBtn.type = 'button';
            addBtn.className = 'pw-btn pw-editor-grid-add-btn';
            addBtn.innerHTML = '<iconify-icon icon="mdi:plus"></iconify-icon>';
            addBtn.title = typeof __ === 'function' ? __('plugins.grid_add_cell') : 'Add grid cell';
            addBtn.addEventListener('click', () => {
                const newCell = { blocks: [] };
                this.data.cells.push(newCell);
                this._renderCell(newCell, this.data.cells.length - 1);
            });

            toolbar.appendChild(colLabel);
            toolbar.appendChild(colSelect);
            toolbar.appendChild(minLabel);
            toolbar.appendChild(minInput);
            toolbar.appendChild(pxLabel);
            toolbar.appendChild(addBtn);
            this.wrapper.appendChild(toolbar);
        }

        this.gridContainer = document.createElement('div');
        this.gridContainer.classList.add('pw-editor-grid-container');
        this._updateGridStyle();
        this.wrapper.appendChild(this.gridContainer);

        this.data.cells.forEach((cell, index) => {
            this._renderCell(cell, index);
        });

        return this.wrapper;
    }

    _updateGridStyle() {
        if (!this.gridContainer) return;
        this.gridContainer.style.gridTemplateColumns =
            `repeat(auto-fill, minmax(min(${this.data.minWidth}px, 100%), 1fr))`;
    }

    _renderCell(cell, index) {
        const cellEl = document.createElement('div');
        cellEl.classList.add('pw-editor-grid-cell');

        if (!this.readOnly) {
            const delBtn = document.createElement('button');
            delBtn.type = 'button';
            delBtn.className = 'pw-editor-grid-cell-del';
            delBtn.innerHTML = '<iconify-icon icon="mdi:close"></iconify-icon>';
            delBtn.title = typeof __ === 'function' ? __('plugins.grid_remove_cell') : 'Remove cell';
            delBtn.addEventListener('click', () => {
                if (this.data.cells.length <= 1) return;
                const idx = this.data.cells.indexOf(cell);
                if (idx > -1) {
                    const editor = this._nestedEditors[idx];
                    if (editor) {
                        try { editor.destroy(); } catch (_) {}
                    }
                    this._nestedEditors.splice(idx, 1);
                    this.data.cells.splice(idx, 1);
                    cellEl.remove();
                }
            });
            cellEl.appendChild(delBtn);
        }

        const editorHolder = document.createElement('div');
        const holderId = 'pw-grid-editor-' + Date.now() + '-' + Math.random().toString(36).substr(2, 6);
        editorHolder.id = holderId;
        editorHolder.classList.add('pw-editor-grid-editor', 'pw-editor-nested');
        cellEl.appendChild(editorHolder);

        cellEl.addEventListener('click', (e) => e.stopPropagation());
        cellEl.addEventListener('keydown', (e) => e.stopPropagation());
        cellEl.addEventListener('mousedown', (e) => e.stopPropagation());

        this.gridContainer.appendChild(cellEl);
        this._initNestedEditor(holderId, cell, index);
    }

    _initNestedEditor(holderId, cell, index) {
        const tools = window.PW_EDITOR_TOOLS || {
            header: { class: Header, config: { levels: [2, 3, 4, 5, 6], defaultLevel: 3 } },
            list: { class: EditorjsList, inlineToolbar: true },
            delimiter: Delimiter
        };

        const editorInstance = new EditorJS({
            holder: holderId,
            data: { blocks: cell.blocks || [] },
            placeholder: typeof __ === 'function' ? __('plugins.grid_cell_placeholder') : 'Grid cell content…',
            minHeight: 0,
            i18n: window.PW_EDITOR_I18N_CONFIG || undefined,
            tools: tools,
            readOnly: this.readOnly,
            onChange: () => {
                if (window.pwEditorInitializing) return;
                if (typeof hasUnsavedChanges !== 'undefined') hasUnsavedChanges = true;
                if (typeof autoSaveTimeout !== 'undefined') clearTimeout(autoSaveTimeout);
                if (typeof saveCurrentDraft === 'function') {
                    autoSaveTimeout = setTimeout(() => saveCurrentDraft(true), 1000);
                }
            }
        });

        this._nestedEditors[index] = editorInstance;
    }

    async save() {
        const cells = [];
        for (let i = 0; i < this.data.cells.length; i++) {
            let blocks = [];
            const editor = this._nestedEditors[i];
            if (editor) {
                try {
                    const output = await editor.save();
                    blocks = output.blocks || [];
                } catch (_) {
                    blocks = this.data.cells[i].blocks || [];
                }
            }
            cells.push({ blocks });
        }
        return {
            columns: this.data.columns,
            minWidth: this.data.minWidth,
            cells: cells
        };
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
