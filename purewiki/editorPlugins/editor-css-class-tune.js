/**
 * PureWiki - CSS Class Tune
 *
 * Apply custom CSS classes to any block in Editor.js.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */
class CssClassTune {
    static get isTune() {
        return true;
    }

    constructor({ api, data, config, block }) {
        this.api = api;
        this.block = block;
        this.data = {
            cssClass: data?.cssClass || ''
        };
        this._wrapper = null;
    }

    render() {
        return {
            icon: '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M9.4 16.6L4.8 12l4.6-4.6L8 6l-6 6l6 6zm5.2 0l4.6-4.6l-4.6-4.6L16 6l6 6l-6 6z"/></svg>',
            label: this.data.cssClass ? 'CSS: ' + this.data.cssClass : 'CSS Class',
            onActivate: async () => {
                const result = await openDialog({
                    title: typeof __ === 'function' ? __('plugins.css_class') : 'CSS Class',
                    text: 'Enter one or more CSS class names (space-separated):',
                    type: 'prompt',
                    placeholder: typeof __ === 'function' ? __('plugins.css_class_placeholder') : 'e.g. my-class another-class',
                    defaultValue: this.data.cssClass,
                    confirmText: 'Apply',
                    cancelText: 'Cancel'
                });

                if (result !== null && result !== undefined) {
                    const value = (typeof result === 'object' ? result.value : result) || '';
                    this.data.cssClass = value.trim();
                    this._applyClasses();

                    if (typeof hasUnsavedChanges !== 'undefined') hasUnsavedChanges = true;
                    if (typeof autoSaveTimeout !== 'undefined') clearTimeout(autoSaveTimeout);
                    if (typeof saveCurrentDraft === 'function') {
                        autoSaveTimeout = setTimeout(() => saveCurrentDraft(true), 1000);
                    }
                }
            },
            isActive: !!this.data.cssClass
        };
    }

    wrap(blockContent) {
        this._wrapper = document.createElement('div');
        this._applyClasses();
        this._wrapper.appendChild(blockContent);
        return this._wrapper;
    }

    _applyClasses() {
        if (!this._wrapper) return;
        this._wrapper.className = '';
        if (this.data.cssClass) {
            const safe = this.data.cssClass.replace(/[^a-zA-Z0-9\-_ ]/g, '');
            safe.split(/\s+/).filter(Boolean).forEach(cls => {
                this._wrapper.classList.add(cls);
            });
        }
    }

    save() {
        if (!this.data.cssClass) {
            return undefined;
        }
        return {
            cssClass: this.data.cssClass
        };
    }
}
