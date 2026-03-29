/**
 * PureWiki - Duplicate Tune
 *
 * Block tune to duplicate the current block.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */
class DuplicateBlockTune {
    static get isTune() {
        return true;
    }

    constructor({ api, data, config, block }) {
        this.api = api;
        this.block = block;
    }

    render() {
        return {
            icon: '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2m0 16H8V7h11z"/></svg>',
            label: typeof __ === 'function' ? __('editor.tune_duplicate') : 'Duplicate',
            onActivate: async () => {
                const currentIndex = this.api.blocks.getCurrentBlockIndex();
                const blockData = await this.block.save();

                this.api.blocks.insert(
                    blockData.tool,
                    blockData.data,
                    {},
                    currentIndex + 1
                );

                if (typeof hasUnsavedChanges !== 'undefined') hasUnsavedChanges = true;
                if (typeof autoSaveTimeout !== 'undefined') clearTimeout(autoSaveTimeout);
                if (typeof saveCurrentDraft === 'function') {
                    autoSaveTimeout = setTimeout(() => saveCurrentDraft(true), 1000);
                }
            }
        };
    }

    save() {
        return undefined;
    }
}
