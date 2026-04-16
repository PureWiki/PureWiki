/**
 * PureWiki - Hidden Block Tune
 *
 * Marks any block as hidden from the rendered page. Hidden blocks remain
 * visible and editable in the editor, but are skipped during rendering.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */
class HiddenBlockTune {
    static get isTune() {
        return true;
    }

    constructor({ api, data, config, block }) {
        this.api = api;
        this.block = block;
        this.data = {
            hidden: data?.hidden === true
        };
        this._wrapper = null;
    }

    render() {
        const label = typeof __ === 'function' ? __('plugins.hidden_block') : 'Hidden';
        return {
            icon: '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M11.83 9L15 12.16V12a3 3 0 0 0-3-3zm-4.3.8l1.55 1.55c-.05.21-.08.42-.08.65a3 3 0 0 0 3 3c.22 0 .44-.03.65-.08l1.55 1.55c-.67.33-1.41.53-2.2.53a5 5 0 0 1-5-5c0-.79.2-1.53.53-2.2M2 4.27l2.28 2.28.45.45C3.08 8.3 1.78 10 1 12c1.73 4.39 6 7.5 11 7.5c1.55 0 3.03-.3 4.38-.84l.43.42L19.73 22L21 20.73L3.27 3M12 7a5 5 0 0 1 5 5c0 .64-.13 1.26-.36 1.82l2.93 2.93c1.5-1.25 2.7-2.89 3.43-4.75c-1.73-4.39-6-7.5-11-7.5c-1.4 0-2.74.25-3.98.7l2.16 2.16C10.74 7.13 11.35 7 12 7z"/></svg>',
            label,
            onActivate: () => {
                this.data.hidden = !this.data.hidden;
                this._applyStyle();

                if (typeof hasUnsavedChanges !== 'undefined') hasUnsavedChanges = true;
                if (typeof autoSaveTimeout !== 'undefined') clearTimeout(autoSaveTimeout);
                if (typeof saveCurrentDraft === 'function') {
                    autoSaveTimeout = setTimeout(() => saveCurrentDraft(true), 1000);
                }
            },
            isActive: this.data.hidden
        };
    }

    wrap(blockContent) {
        this._wrapper = document.createElement('div');
        this._applyStyle();
        this._wrapper.appendChild(blockContent);
        return this._wrapper;
    }

    _applyStyle() {
        if (!this._wrapper) return;
        const existing = this._wrapper.querySelector('.pw-block-hidden-badge');
        if (existing) existing.remove();
        if (this.data.hidden) {
            this._wrapper.classList.add('pw-block-hidden-indicator');
            const badge = document.createElement('div');
            badge.className = 'pw-block-hidden-badge';
            badge.textContent = typeof __ === 'function' ? __('plugins.hidden_block') : 'Hidden';
            this._wrapper.prepend(badge);
        } else {
            this._wrapper.classList.remove('pw-block-hidden-indicator');
        }
    }

    save() {
        if (!this.data.hidden) {
            return undefined;
        }
        return { hidden: true };
    }
}
