/**
 * PureWiki - Dedicated Search Page
 *
 * Loads results for the dedicated search page with tag filter and sorting.
 * Results are rendered with the result template of the active theme.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

(function() {
    const root = document.getElementById('pw-search-page');
    if (!root) return;

    const form = document.getElementById('pw-search-form');
    const tagsBox = document.getElementById('pw-search-tags');
    const tagsCount = document.getElementById('pw-search-tags-count');
    const tagsFilter = document.getElementById('pw-search-tags-filter');
    const countEl = document.getElementById('pw-search-count');
    const listEl = document.getElementById('pw-search-result-list');
    const moreBtn = document.getElementById('pw-search-more');
    const template = document.getElementById('pw-search-result-template');
    const tagBoxes = [...form.querySelectorAll('input[name="tags"]')];

    const apiEndpoint = root.dataset.apiEndpoint;
    const lang = root.dataset.lang || '';
    const searchUrl = root.dataset.searchUrl;
    let texts = {};
    try {
        texts = JSON.parse(root.dataset.texts || '{}');
    } catch (e) {
        texts = {};
    }

    let offset = 0;
    let searchTimer = null;
    let requestId = 0;

    /** Escapes string for HTML output */
    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    /** Formats date as d.m.Y*/
    function formatDate(date) {
        const parts = date.split('-');
        return parts.length === 3 ? `${parts[2]}.${parts[1]}.${parts[0]}` : date;
    }

    /** Return current filters as URL parameters */
    function getFilterParams() {
        const params = new URLSearchParams();
        const q = form.elements.q.value.trim();
        const tags = tagBoxes.filter(box => box.checked).map(box => box.value);
        if (q) params.set('q', q);
        if (tags.length) params.set('tags', tags.join(','));
        if (form.elements.sort.value !== 'relevance') params.set('sort', form.elements.sort.value);
        return params;
    }

    /** Shows the number of selected tags next to dropdown label */
    function updateTagCount() {
        const count = tagBoxes.filter(box => box.checked).length;
        tagsCount.textContent = count ? `(${count})` : '';
    }

    /** Fills form from URL so searches can be linked */
    function readUrl() {
        const params = new URLSearchParams(window.location.search);
        form.elements.q.value = params.get('q') || '';
        const sort = params.get('sort');
        if (sort && form.elements.sort.querySelector(`option[value="${CSS.escape(sort)}"]`)) {
            form.elements.sort.value = sort;
        }
        const tags = (params.get('tags') || '').split(',').map(t => t.trim().toLowerCase()).filter(Boolean);
        tagBoxes.forEach(box => { box.checked = tags.includes(box.value.toLowerCase()); });
        updateTagCount();
    }

    /** Fills elements of a template field */
    function fillField(card, name, callback) {
        card.querySelectorAll(`[data-pw-field="${name}"]`).forEach(callback);
    }

    /** Builds a result card from theme template */
    function renderResult(result) {
        const card = template.content.firstElementChild.cloneNode(true);
        const url = (window.PW_BASE_PATH || '') + result.path;

        fillField(card, 'link', el => { el.href = url; });
        fillField(card, 'title', el => {
            el.textContent = result.title;
            if (el.tagName === 'A') el.href = url;
        });
        fillField(card, 'path', el => { el.textContent = result.path; });
        fillField(card, 'excerpt', el => {
            if (result.excerpt) el.innerHTML = result.excerpt;
            else el.hidden = true;
        });
        fillField(card, 'description', el => {
            if (result.description) el.textContent = result.description;
            else el.hidden = true;
        });
        fillField(card, 'date', el => {
            if (!result.date) {
                el.hidden = true;
                return;
            }
            el.textContent = formatDate(result.date);
            if (el.tagName === 'TIME') el.setAttribute('datetime', result.date);
        });
        fillField(card, 'tags', el => {
            const tags = Array.isArray(result.tags) ? result.tags : [];
            if (!tags.length) {
                el.hidden = true;
                return;
            }
            el.innerHTML = tags.map(t =>
                `<a href="${escapeHtml(searchUrl + '?tags=' + encodeURIComponent(t))}">${escapeHtml(t)}</a>`
            ).join(', ');
        });

        return card;
    }

    /** Search function */
    async function runSearch(append = false) {
        const filters = getFilterParams();
        const query = filters.toString();
        history.replaceState(null, '', window.location.pathname + (query ? '?' + query : ''));

        if (!append) offset = 0;
        const params = new URLSearchParams(filters);
        params.set('action', 'search');
        params.set('extended', '1');
        params.set('lang', lang);
        params.set('offset', String(offset));

        // Ignore old requests
        const currentId = ++requestId;
        try {
            const res = await fetch(`${apiEndpoint}?${params.toString()}`);
            const data = await res.json();
            if (currentId !== requestId) return;

            if (!data.success) throw new Error(data.message || 'Search failed');

            if (!append) listEl.innerHTML = '';
            data.results.forEach(r => listEl.appendChild(renderResult(r)));

            countEl.textContent = data.total ? (texts.result_count || '%d').replace('%d', data.total) : (texts.no_results || '');
            moreBtn.hidden = !data.has_more;
        } catch (e) {
            if (currentId !== requestId) return;
            if (window.PW_DEBUG) console.log('Search failed', e);
            listEl.innerHTML = '';
            countEl.textContent = texts.failed || '';
            moreBtn.hidden = true;
        }
    }

    form.addEventListener('submit', (e) => {
        e.preventDefault();
        clearTimeout(searchTimer);
        runSearch();
    });

    form.elements.q.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => runSearch(), 300);
    });

    form.addEventListener('change', (e) => {
        if (e.target.name === 'tags') updateTagCount();
        if (e.target.name === 'tags' || e.target.name === 'sort') runSearch();
    });

    if (tagsFilter) {
        tagsFilter.addEventListener('input', () => {
            const term = tagsFilter.value.trim().toLowerCase();
            tagBoxes.forEach(box => {
                box.closest('label').hidden = term !== '' && !box.value.toLowerCase().includes(term);
            });
        });
    }

    // Close the dropdown on outside click and Esc
    document.addEventListener('click', (e) => {
        if (tagsBox.open && !tagsBox.contains(e.target)) tagsBox.open = false;
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && tagsBox.open) tagsBox.open = false;
    });

    moreBtn.addEventListener('click', () => {
        offset = listEl.children.length;
        runSearch(true);
    });

    readUrl();
    runSearch();
})();
