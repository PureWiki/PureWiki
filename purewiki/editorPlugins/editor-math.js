/**
 * PureWiki - Math Tool (KaTeX)
 *
 * Math editor block for Editor.js with live preview using KaTeX.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */
class MathTool {
    static get isReadOnlySupported() {
        return true;
    }

    static get toolbox() {
        return {
            title: typeof __ === 'function' ? __('plugins.math') : 'Math',
            icon: `<svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><path fill="currentColor" d="M12.187 4.14c-.964-.723-2.345-.07-2.398 1.134L9.712 7H12a1 1 0 1 1 0 2H9.623l-.393 8.85c-.13 2.931-3.593 4.41-5.801 2.479l-.087-.076a1 1 0 0 1 1.317-1.506l.087.076c.946.829 2.43.194 2.486-1.062L7.622 9H6a1 1 0 0 1 0-2h1.71l.08-1.815c.126-2.81 3.347-4.332 5.597-2.645l.213.16a1 1 0 1 1-1.2 1.6zm.895 8.906a.5.5 0 0 1 .693.225l.813 1.727l-3.295 3.295a1 1 0 0 0 1.414 1.414l2.786-2.786l.78 1.657a2.5 2.5 0 0 0 3.853.864l.51-.42a1 1 0 1 0-1.272-1.543l-.51.42a.5.5 0 0 1-.771-.172l-1.087-2.309l2.711-2.711a1 1 0 1 0-1.414-1.414l-2.202 2.202l-.506-1.075a2.5 2.5 0 0 0-3.467-1.126l-.6.33a1 1 0 0 0 .964 1.752z"/></svg>`
        };
    }

    constructor({ data, config, api, readOnly }) {
        this.api = api;
        this.readOnly = readOnly;
        this.data = {
            math: data.math || ''
        };

        this.nodes = {
            holder: null,
            textarea: null,
            preview: null
        };

        this._renderTimeout = null;
    }

    /**
     * Renders the Math code into the preview element using KaTeX.
     */
    _renderPreview(code) {
        const preview = this.nodes.preview;
        if (!preview) return;

        if (!code || code.trim() === '') {
            preview.innerHTML = `<span class="pw-math-preview-placeholder" style="color:#666;font-style:italic;">${
                typeof __ === 'function' ? __('plugins.math_placeholder') : 'Enter KaTeX/Math code here...'
            }</span>`;
            return;
        }

        try {
            if (typeof katex === 'undefined') {
                throw new Error("KaTeX is not loaded yet.");
            }
            const html = katex.renderToString(code, {
                displayMode: true,
                throwOnError: false,
                output: 'html'
            });
            preview.innerHTML = html;
        } catch (e) {
            preview.innerHTML = `<span style="color:#c00;font-family:monospace;font-size:0.85rem;">⚠ KaTeX Error: ${e.message}</span>`;
        }
    }

    _schedulePreview(code) {
        clearTimeout(this._renderTimeout);
        this._renderTimeout = setTimeout(() => this._renderPreview(code), 400);
    }

    render() {
        const wrapper = document.createElement('div');
        wrapper.classList.add('pw-editor-math-wrapper');

        const label = document.createElement('div');
        label.classList.add('pw-editor-math-title');
        label.textContent = (typeof __ === 'function' ? __('plugins.math') : 'Math (KaTeX)') + ':';
        wrapper.appendChild(label);

        const inner = document.createElement('div');
        inner.classList.add('pw-editor-math');
        wrapper.appendChild(inner);

        if (this.readOnly) {
            const preview = document.createElement('div');
            preview.classList.add('pw-math-preview', 'pw-math-preview--readonly');
            inner.appendChild(preview);
            this.nodes.holder = wrapper;
            this.nodes.preview = preview;
            this._renderPreview(this.data.math);
            return wrapper;
        }

        // Code textarea
        const textarea = document.createElement('textarea');
        textarea.classList.add('pw-editor-math-textarea');
        textarea.placeholder = typeof __ === 'function' ? __('plugins.math_placeholder') : 'Enter KaTeX/Math code here...';
        textarea.value = this.data.math;
        textarea.spellcheck = false;

        textarea.addEventListener('input', (e) => {
            this.data.math = e.target.value;
            if (!CSS.supports('field-sizing', 'content')) {
                textarea.style.height = 'auto';
                textarea.style.height = textarea.scrollHeight + 'px';
            }
            this._schedulePreview(this.data.math);
        });

        textarea.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') e.stopPropagation();
            if (e.key === 'Backspace' && textarea.selectionStart === 0 && textarea.selectionEnd === 0 && textarea.value.length > 0) {
                e.stopPropagation();
            }
            if (e.key === 'Tab') {
                e.preventDefault();
                const s = textarea.selectionStart;
                textarea.value = textarea.value.substring(0, s) + '    ' + textarea.value.substring(textarea.selectionEnd);
                textarea.selectionStart = textarea.selectionEnd = s + 4;
                this.data.math = textarea.value;
            }
        });

        inner.appendChild(textarea);

        // Separator + Preview label
        const sep = document.createElement('div');
        sep.classList.add('pw-editor-math-sep');
        inner.appendChild(sep);

        const previewLabel = document.createElement('div');
        previewLabel.classList.add('pw-editor-math-preview-label');
        previewLabel.textContent = 'Preview';
        inner.appendChild(previewLabel);

        // Preview container (white background so KaTeX renders correctly)
        const preview = document.createElement('div');
        preview.classList.add('pw-math-preview');
        inner.appendChild(preview);

        this.nodes.holder = wrapper;
        this.nodes.textarea = textarea;
        this.nodes.preview = preview;

        setTimeout(() => {
            if (!CSS.supports('field-sizing', 'content')) {
                textarea.style.height = 'auto';
                textarea.style.height = textarea.scrollHeight + 'px';
            }
            this._renderPreview(this.data.math);
        }, 0);

        return wrapper;
    }

    save() {
        return { math: this.data.math };
    }

    validate(savedData) {
        return !!(savedData.math && savedData.math.trim() !== '');
    }
}
