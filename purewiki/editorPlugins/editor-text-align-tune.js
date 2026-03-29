/**
 * PureWiki - Text Align Tool
 *
 * Text Align tool for Editor.js
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

const PW_ALIGN_OPTIONS = [
    {
        value: 'left',
        get label() { return typeof __ === 'function' ? __('plugins.text_align_left')  : 'Left';    },
        icon: '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path stroke="none" stroke-width="0" fill="currentColor" d="M3 3h18v2H3zm0 4h12v2H3zm0 4h18v2H3zm0 4h12v2H3zm0 4h18v2H3z"/></svg>'
    },
    {
        value: 'center',
        get label() { return typeof __ === 'function' ? __('plugins.text_align_center') : 'Center'; },
        icon: '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path stroke="none" stroke-width="0" fill="currentColor" d="M3 3h18v2H3zm3 4h12v2H6zm-3 4h18v2H3zm3 4h12v2H6zm-3 4h18v2H3z"/></svg>'
    },
    {
        value: 'right',
        get label() { return typeof __ === 'function' ? __('plugins.text_align_right')  : 'Right';  },
        icon: '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path stroke="none" stroke-width="0" fill="currentColor" d="M3 3h18v2H3zm6 4h12v2H9zm-6 4h18v2H3zm6 4h12v2H9zm-6 4h18v2H3z"/></svg>'
    },
    {
        value: 'justify',
        get label() { return typeof __ === 'function' ? __('plugins.text_align_justify') : 'Justify'; },
        icon: '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path stroke="none" stroke-width="0" fill="currentColor" d="M3 3h18v2H3zm0 4h18v2H3zm0 4h18v2H3zm0 4h18v2H3zm0 4h12v2H3z"/></svg>'
    }
];

function pwGetAlignment(wrapper) {
    if (!wrapper) return 'left';
    for (const a of PW_ALIGN_OPTIONS) {
        if (a.value !== 'left' && wrapper.classList.contains(`pw-text-align--${a.value}`)) {
            return a.value;
        }
    }
    return 'left';
}

function pwSetAlignmentOnWrapper(wrapper, value) {
    if (!wrapper) return;
    PW_ALIGN_OPTIONS.forEach(a => {
        if (a.value !== 'left') wrapper.classList.remove(`pw-text-align--${a.value}`);
    });
    if (value && value !== 'left') {
        wrapper.classList.add(`pw-text-align--${value}`);
    }
}

function pwAlignTriggerSave() {
    if (typeof hasUnsavedChanges !== 'undefined') hasUnsavedChanges = true;
    if (typeof autoSaveTimeout !== 'undefined') clearTimeout(autoSaveTimeout);
    if (typeof saveCurrentDraft === 'function') {
        autoSaveTimeout = setTimeout(() => saveCurrentDraft(true), 1000);
    }
}



class TextAlignTune {
    static get isTune() {
        return true;
    }

    static get title() {
        return 'Alignment';
    }

    constructor({ api, data, config, block }) {
        this.api = api;
        this.block = block;
        this.data = {
            alignment: data?.alignment || 'left'
        };
        this._wrapper = null;
    }

    render() {
        const currentAlignment = this._getCurrentAlignment();
        const currentOption = PW_ALIGN_OPTIONS.find(a => a.value === currentAlignment)
            || PW_ALIGN_OPTIONS[0];

        return {
            icon: currentOption.icon,
            label: typeof __ === 'function' ? __('plugins.text_align') : 'Alignment',
            isActive: currentAlignment !== 'left',
            children: {
                items: PW_ALIGN_OPTIONS.map(align => ({
                    icon: align.icon,
                    title: align.label,
                    isActive: () => this._getCurrentAlignment() === align.value,
                    onActivate: () => {
                        this.data.alignment = align.value;
                        pwSetAlignmentOnWrapper(this._wrapper, align.value);
                        pwAlignTriggerSave();
                    }
                }))
            }
        };
    }

    wrap(blockContent) {
        this._wrapper = document.createElement('div');
        this._wrapper.classList.add('pw-text-align-wrapper');
        pwSetAlignmentOnWrapper(this._wrapper, this.data.alignment);
        this._wrapper.appendChild(blockContent);
        return this._wrapper;
    }

    _getCurrentAlignment() {
        return this._wrapper
            ? pwGetAlignment(this._wrapper)
            : (this.data.alignment || 'left');
    }

    save() {
        const alignment = this._getCurrentAlignment();
        if (!alignment || alignment === 'left') {
            return undefined;
        }
        return { alignment };
    }
}



class TextAlignInlineTool {
    static get isInline() {
        return true;
    }

    static get title() {
        return 'Alignment';
    }

    static get sanitize() {
        return {};
    }

    constructor({ api }) {
        this.api = api;
        this._container = null;
        this._alignBtns = {};
        this._currentWrapper = null;
    }

    render() {
        this._container = document.createElement('span');
        this._container.classList.add('pw-align-inline-group');

        PW_ALIGN_OPTIONS.forEach(align => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.classList.add('pw-align-inline-btn');
            btn.innerHTML = align.icon;
            btn.title = align.label;
            btn.dataset.align = align.value;

            btn.addEventListener('mousedown', (e) => {
                e.preventDefault();
                e.stopImmediatePropagation();
                this._applyAlignment(align.value);
            });

            btn.addEventListener('click', (e) => {
                e.stopImmediatePropagation();
            });

            this._alignBtns[align.value] = btn;
            this._container.appendChild(btn);
        });

        return this._container;
    }

    surround(range) {}

    checkState(selection) {
        if (!selection || selection.rangeCount === 0) return false;

        const range = selection.getRangeAt(0);
        this._currentWrapper = this._findWrapper(range.commonAncestorContainer);

        const current = pwGetAlignment(this._currentWrapper);
        Object.entries(this._alignBtns).forEach(([value, btn]) => {
            btn.classList.toggle('pw-align-active', value === current);
        });

        return false;
    }

    _findWrapper(node) {
        while (node && node !== document.body) {
            if (node.classList && node.classList.contains('pw-text-align-wrapper')) {
                return node;
            }
            node = node.parentNode;
        }
        return null;
    }

    _applyAlignment(value) {
        if (!this._currentWrapper) return;

        pwSetAlignmentOnWrapper(this._currentWrapper, value);

        Object.entries(this._alignBtns).forEach(([v, btn]) => {
            btn.classList.toggle('pw-align-active', v === value);
        });

        pwAlignTriggerSave();
    }
}
