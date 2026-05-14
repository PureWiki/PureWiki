/**
 * PureWiki - Inline Code Tool
 *
 * Inline code tool for Editor.js.
 * Custom version that allows multiple code tags in the same block.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */
class InlineCode {

    static get isInline() {
        return true;
    }

    static get CSS() {
        return 'inline-code';
    }

    static get sanitize() {
        return {
            code: {
                class: InlineCode.CSS
            }
        };
    }

    constructor({ api }) {
        this.api = api;
        this.button = null;
        this.tag = 'CODE';

        this.iconClasses = {
            base: this.api.styles.inlineToolButton,
            active: this.api.styles.inlineToolButtonActive
        };
    }

    /**
     * Wrap and Unwrap selected text
     *
     * @param {Range} range selected text
     */
    surround(range) {
        if (!range) {
            return;
        }

        const codeWrapper = this.api.selection.findParentTag(this.tag, InlineCode.CSS);

        if (codeWrapper) {
            this.unwrap(codeWrapper);
        } else {
            this.wrap(range);
        }
    }

    /**
     * Wrap selection with code tag
     *
     * @param {Range} range
     */
    wrap(range) {
        const tag = document.createElement(this.tag);

        tag.classList.add(InlineCode.CSS);
        tag.appendChild(range.extractContents());
        range.insertNode(tag);

        this.api.selection.expandToTag(tag);
    }

    /**
     * Unwrap code tag
     *
     * @param {HTMLElement} codeWrapper Code wrapper tag
     */
    unwrap(codeWrapper) {
        this.api.selection.expandToTag(codeWrapper);

        const sel = window.getSelection();
        const range = sel.getRangeAt(0);

        const unwrappedContent = range.extractContents();

        codeWrapper.parentNode.removeChild(codeWrapper);

        range.insertNode(unwrappedContent);

        sel.removeAllRanges();
        sel.addRange(range);
    }

    /**
     * Check and change state for selection
     */
    checkState() {
        const codeTag = this.api.selection.findParentTag(this.tag, InlineCode.CSS);

        this.button.classList.toggle(this.iconClasses.active, !!codeTag);
    }

    render() {
        this.button = document.createElement('button');
        this.button.type = 'button';
        this.button.classList.add(this.iconClasses.base);
        this.button.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 8L5 12L9 16"/><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 8L19 12L15 16"/></svg>';

        return this.button;
    }
}