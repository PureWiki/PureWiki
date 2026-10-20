/**
 * PureWiki - Video Tool
 *
 * Embeds YouTube and Vimeo videos or own video files from Media Manager.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

class VideoTool {
    static get toolbox() {
        return {
            title: typeof __ === 'function' ? __('plugins.video') : 'Video',
            icon: '<svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><path fill="currentColor" d="m18 16l-4-3.2V16H6V8h8v3.2L18 8m2-4H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2"/></svg>'
        };
    }

    /** Returns provider and id of a supported video url or null. Server also validates on render */
    static parseUrl(url) {
        const value = (url || '').trim();

        if (value.startsWith('/') && !value.startsWith('//')) {
            return /^\/[^\x00-\x1f"'<>\\?#]+\.(mp4|webm|mov|ogv)$/i.test(value) && !value.includes('..')
                ? { provider: 'file', id: value }
                : null;
        }

        let parsed;
        try {
            parsed = new URL(value);
        } catch (e) {
            return null;
        }
        if (!['http:', 'https:'].includes(parsed.protocol)) return null;

        const host = parsed.hostname.toLowerCase().replace(/^(www|m)\./, '');
        if (['youtu.be', 'youtube.com', 'youtube-nocookie.com'].includes(host)) {
            const match = parsed.pathname.match(/^\/(?:shorts|embed|live)\/([^/]+)/);
            const id = host === 'youtu.be' ? parsed.pathname.split('/')[1] : (match ? match[1] : parsed.searchParams.get('v'));
            return id && /^[A-Za-z0-9_-]{11}$/.test(id) ? { provider: 'youtube', id } : null;
        }
        const vimeo = host === 'vimeo.com' ? parsed.pathname.match(/^\/(\d+)(?:\/[A-Za-z0-9]+)?\/?$/)
            : host === 'player.vimeo.com' ? parsed.pathname.match(/^\/video\/(\d+)\/?$/)
            : null;
        return vimeo ? { provider: 'vimeo', id: vimeo[1] } : null;
    }

    /** Returns the start time in seconds from a t or start parameter */
    static parseStart(url) {
        let parsed;
        try {
            parsed = new URL(url, window.location.origin);
        } catch (e) {
            return 0;
        }
        const raw = parsed.searchParams.get('t') || parsed.searchParams.get('start') || (parsed.hash.match(/t=([^&]+)/) || [])[1] || '';
        const match = raw.match(/^(?:(\d+)h)?(?:(\d+)m)?(?:(\d+)s?)?$/);
        if (!raw || !match) return 0;
        return (parseInt(match[1] || 0, 10) * 3600) + (parseInt(match[2] || 0, 10) * 60) + parseInt(match[3] || 0, 10);
    }

    constructor({ data, readOnly }) {
        this.data = {
            url: data.url || '',
            aspect: data.aspect || '16:9',
            start: parseInt(data.start, 10) || 0,
            autoplay: !!data.autoplay,
            loop: !!data.loop,
            muted: !!data.muted,
            hideControls: !!data.hideControls
        };
        this.readOnly = readOnly;
    }

    render() {
        this.wrapper = document.createElement('div');
        this.wrapper.classList.add('pw-editor-video-wrapper');

        if (this.data.url) {
            this._createVideoView();
        } else {
            this._createSelectView();
        }

        return this.wrapper;
    }

    save() {
        return { ...this.data };
    }

    validate(savedData) {
        return savedData.url.trim() !== '';
    }

    onMediaSelected(url) {
        this.data.url = url;
        this._createVideoView();
        this._markChanged();
    }

    _t(key, fallback) {
        return typeof __ === 'function' ? __(key) : fallback;
    }

    _markChanged() {
        if (window.pwEditorInitializing) return;
        if (typeof hasUnsavedChanges !== 'undefined') hasUnsavedChanges = true;
        if (typeof autoSaveTimeout !== 'undefined') clearTimeout(autoSaveTimeout);
        if (typeof saveCurrentDraft === 'function') {
            autoSaveTimeout = setTimeout(() => saveCurrentDraft(true), 1000);
        }
    }

    /** Returns the block title row, optionally with the remove button */
    _titleHtml(withReset) {
        return `
            <div class="pw-editor-video-header">
                <strong class="pw-editor-video-title">${this._t('plugins.video', 'Video')}</strong>
                ${withReset ? `<button type="button" class="pw-btn pw-btn-sm pw-editor-video-reset" title="${this._t('plugins.video_remove', 'Remove video')}"><iconify-icon icon="mdi:close"></iconify-icon></button>` : ''}
            </div>`;
    }

    _createSelectView() {
        this.wrapper.innerHTML = `
            ${this._titleHtml(false)}
            <div class="pw-editor-video-select">
                <div class="pw-editor-video-url-row">
                    <input type="url" class="pw-input pw-editor-video-url" placeholder="${this._t('plugins.video_url_placeholder', 'Paste a YouTube or Vimeo link...')}">
                    <button type="button" class="pw-btn pw-btn-primary pw-editor-video-embed">${this._t('plugins.video_embed', 'Embed')}</button>
                </div>
                <div class="pw-editor-video-or">${this._t('plugins.video_or', 'or')}</div>
                <button type="button" class="pw-btn pw-editor-video-file"><iconify-icon icon="mdi:file-video-outline" class="pw-icon-left"></iconify-icon>${this._t('plugins.video_select_file', 'Select Video File')}</button>
            </div>`;

        const urlInput = this.wrapper.querySelector('.pw-editor-video-url');
        const applyUrl = () => {
            const url = urlInput.value.trim();
            if (!url) return;
            if (!VideoTool.parseUrl(url)) {
                notify(this._t('plugins.video_invalid_url', 'Unsupported video link.'), 'error');
                return;
            }
            this.data.start = VideoTool.parseStart(url);
            this.onMediaSelected(url);
        };

        this.wrapper.querySelector('.pw-editor-video-embed').addEventListener('click', applyUrl);
        urlInput.addEventListener('keydown', (e) => {
            // Enter embeds the link instead of creating a new block
            if (e.key !== 'Enter') return;
            e.preventDefault();
            e.stopPropagation();
            applyUrl();
        });

        this.wrapper.querySelector('.pw-editor-video-file').addEventListener('click', () => {
            if (typeof openImageSelectionDialog === 'function') {
                openImageSelectionDialog(this, { mediaType: 'video' });
            } else {
                notify(this._t('plugins.video_not_available', 'Media selection dialog not available.'), 'error');
            }
        });
    }

    _createVideoView() {
        const video = VideoTool.parseUrl(this.data.url);
        const isFile = video && video.provider === 'file';
        const aspectCss = () => this.data.aspect.replace(':', ' / ');
        const providerNames = { youtube: 'YouTube', vimeo: 'Vimeo' };

        const preview = isFile
            ? `<video class="pw-editor-video-preview" controls preload="metadata"></video>`
            : `<div class="pw-editor-video-preview pw-editor-video-external">
                    <iconify-icon icon="${video ? 'mdi:' + video.provider : 'mdi:alert-outline'}" class="pw-editor-video-provider-icon"></iconify-icon>
                    <strong class="pw-editor-video-provider"></strong>
                    <span class="pw-editor-video-source"></span>
                    <span class="pw-editor-video-hint"></span>
                </div>`;

        const checkbox = ([name, labelKey, fallback]) => `
            <label class="pw-editor-video-check">
                <input type="checkbox" class="pw-checkbox" data-option="${name}">
                <span>${this._t(labelKey, fallback)}</span>
            </label>`;
        const fileOptions = [
            ['autoplay', 'plugins.video_autoplay', 'Autoplay'],
            ['loop', 'plugins.video_loop', 'Loop'],
            ['muted', 'plugins.video_muted', 'Muted'],
            ['hideControls', 'plugins.video_hide_controls', 'Hide Controls (Loop only)']
        ];
        const aspectOptions = ['16:9', '4:3', '1:1', '9:16']
            .map(a => `<option value="${a}"${a === this.data.aspect ? ' selected' : ''}>${a}</option>`).join('');

        this.wrapper.innerHTML = `
            ${this._titleHtml(!this.readOnly)}
            ${preview}
            ${this.readOnly ? '' : `
            <div class="pw-editor-video-options">
                <div class="pw-editor-video-field">
                    <label class="pw-editor-video-label">${this._t('plugins.video_aspect', 'Aspect Ratio')}</label>
                    <select class="pw-input pw-editor-video-aspect">${aspectOptions}</select>
                </div>
                <div class="pw-editor-video-field">
                    <label class="pw-editor-video-label">${this._t('plugins.video_start', 'Start (seconds)')}</label>
                    <input type="number" min="0" class="pw-input pw-editor-video-start" placeholder="0">
                </div>
            </div>
            ${isFile ? `<div class="pw-editor-video-checks">${fileOptions.map(checkbox).join('')}</div>` : ''}`}`;

        const previewEl = this.wrapper.querySelector('.pw-editor-video-preview');
        previewEl.style.aspectRatio = aspectCss();

        if (isFile) {
            previewEl.src = (window.PW_BASE_PATH || '') + this.data.url;
        } else {
            this.wrapper.querySelector('.pw-editor-video-provider').textContent = video ? providerNames[video.provider] : this._t('plugins.video_invalid_url', 'Unsupported video link.');
            this.wrapper.querySelector('.pw-editor-video-source').textContent = this.data.url;
            if (video) this.wrapper.querySelector('.pw-editor-video-hint').textContent = this._t('plugins.video_external_hint', 'Visitors load this video by clicking the play button.');
        }

        if (this.readOnly) return;

        this.wrapper.querySelector('.pw-editor-video-reset').addEventListener('click', () => {
            Object.assign(this.data, { url: '', start: 0 });
            this._createSelectView();
            this._markChanged();
        });

        this.wrapper.querySelector('.pw-editor-video-aspect').addEventListener('change', (e) => {
            this.data.aspect = e.target.value;
            previewEl.style.aspectRatio = aspectCss();
            this._markChanged();
        });

        const startInput = this.wrapper.querySelector('.pw-editor-video-start');
        startInput.value = this.data.start || '';
        startInput.addEventListener('input', () => {
            this.data.start = Math.max(0, parseInt(startInput.value, 10) || 0);
            this._markChanged();
        });

        const optionBox = (name) => this.wrapper.querySelector(`[data-option="${name}"]`);
        const syncOptions = () => {
            if (!isFile) return;
            // Hiding controls only works with loop and needs autoplay. Also seems like Browsers only autoplay muted videos.
            const hideControls = this.data.loop && this.data.hideControls;
            const autoplay = this.data.autoplay || hideControls;
            Object.assign(optionBox('hideControls'), { checked: hideControls, disabled: !this.data.loop });
            Object.assign(optionBox('autoplay'), { checked: autoplay, disabled: hideControls });
            Object.assign(optionBox('muted'), { checked: this.data.muted || autoplay, disabled: autoplay });
        };

        this.wrapper.querySelectorAll('input[data-option]').forEach(box => {
            const option = box.dataset.option;
            box.checked = !!this.data[option];
            box.addEventListener('change', () => {
                this.data[option] = box.checked;
                syncOptions();
                this._markChanged();
            });
        });
        syncOptions();
    }
}
