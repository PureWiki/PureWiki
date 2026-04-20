/**
 * PureWiki - PagePicker Component
 *
 * A reusable component to add autocompletion for wiki page paths to any input field.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

class PagePicker {
    /**
     * @param {HTMLInputElement} inputElement The input field to attach the picker to.
     * @param {Object} options Configuration options.
     * @param {Function} options.onSelect Callback when a page is selected.
     * @param {Function} options.onValidate Callback to validate the current path.
     */
    constructor(inputElement, options = {}) {
        this.input = inputElement;
        this.options = options;
        this.pages = [];
        this.dropdown = null;
        this.selectedIndex = -1;
        this.isVisible = false;
        this.debounceTimer = null;

        this.init();
    }

    async init() {
        try {
            const result = await apiCall('list_pages');
            if (result && result.success) {
                this.pages = result.pages;
            }
        } catch (err) {
            console.error('Failed to load pages for PagePicker:', err);
        }

        this.setupEvents();
        this.createDropdown();
    }

    createDropdown() {
        this.dropdown = document.createElement('div');
        this.dropdown.className = 'pw-pagepicker-dropdown';
        document.body.appendChild(this.dropdown);
    }

    setupEvents() {
        this.input.addEventListener('input', () => {
            clearTimeout(this.debounceTimer);
            this.debounceTimer = setTimeout(() => this.handleInput(), 200);
        });
        this.input.addEventListener('keydown', (e) => this.handleKeydown(e));

        document.addEventListener('click', (e) => {
            if (e.target !== this.input && !this.dropdown.contains(e.target)) {
                this.hide();
            }
        });

        window.addEventListener('scroll', () => this.updatePosition(), true);
        window.addEventListener('resize', () => this.updatePosition());
    }

    handleInput() {
        if (this.isSelecting) {
            this.isSelecting = false;
            return;
        }
        const value = this.input.value.trim().toLowerCase();
        if (value.length < 1) {
            this.hide();
            if (this.options.onValidate) this.options.onValidate(false);
            return;
        }

        let matches = this.pages.filter(p => 
            p.path.toLowerCase().includes(value) || 
            p.title.toLowerCase().includes(value)
        );

        // Sort: exact path first, then exact title, then prefix path, then prefix title, then alphabetical by path
        matches.sort((a, b) => {
            const aPath = a.path.toLowerCase();
            const bPath = b.path.toLowerCase();
            const aTitle = a.title.toLowerCase();
            const bTitle = b.title.toLowerCase();

            //exact path
            if (aPath === value || bPath === value) return aPath === value ? -1 : 1;
            
            //exact title
            if (aTitle === value || bTitle === value) return aTitle === value ? -1 : 1;
            
            //prefix path
            if (aPath.startsWith(value) && !bPath.startsWith(value)) return -1;
            if (!aPath.startsWith(value) && bPath.startsWith(value)) return 1;
            
            //prefix title
            if (aTitle.startsWith(value) && !bTitle.startsWith(value)) return -1;
            if (!aTitle.startsWith(value) && bTitle.startsWith(value)) return 1;

            //Alphabetical by path
            return aPath.localeCompare(bPath);
        });

        matches = matches.slice(0, 10);

        if (matches.length > 0) {
            this.renderMatches(matches);
            this.show();
            const exactMatch = this.pages.find(p => p.path.toLowerCase() === this.input.value.trim().toLowerCase());
            if (this.options.onValidate) this.options.onValidate(!!exactMatch);
        } else {
            this.hide();
            if (this.options.onValidate) this.options.onValidate(false);
        }
    }

    renderMatches(matches) {
        this.dropdown.innerHTML = '';
        this.selectedIndex = -1;

        matches.forEach((match, index) => {
            const item = document.createElement('div');
            item.className = 'pw-pagepicker-item';
            item.innerHTML = `
                <div class="pw-pagepicker-title">${this.highlight(match.title)}</div>
                <div class="pw-pagepicker-path">${this.highlight(match.path)}</div>
            `;
            item.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.select(match);
            });
            this.dropdown.appendChild(item);
        });

        if (this.isVisible) this.updatePosition();
    }

    highlight(text) {
        const query = this.input.value.trim();
        if (!query) return text;
        const escaped = query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        const regex = new RegExp(`(${escaped})`, 'gi');
        return text.replace(regex, '<mark>$1</mark>');
    }

    handleKeydown(e) {
        if (!this.isVisible) return;

        const items = this.dropdown.querySelectorAll('.pw-pagepicker-item');

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            this.selectedIndex = (this.selectedIndex + 1) % items.length;
            this.updateSelection(items);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            this.selectedIndex = (this.selectedIndex - 1 + items.length) % items.length;
            this.updateSelection(items);
        } else if (e.key === 'Enter') {
            if (this.selectedIndex > -1) {
                e.preventDefault();
                items[this.selectedIndex].click();
            }
        } else if (e.key === 'Escape') {
            this.hide();
        }
    }

    updateSelection(items) {
        items.forEach((item, index) => {
            item.classList.toggle('pw-active', index === this.selectedIndex);
        });
        if (this.selectedIndex > -1) {
            items[this.selectedIndex].scrollIntoView({ block: 'nearest' });
        }
    }

    select(match) {
        this.isSelecting = true;
        this.input.value = match.path;
        this.hide();
        if (this.options.onSelect) this.options.onSelect(match);
        if (this.options.onValidate) this.options.onValidate(true);
        // Trigger input event manually
        this.input.dispatchEvent(new Event('input'));
    }

    updatePosition() {
        const rect = this.input.getBoundingClientRect();
        this.dropdown.style.top = `${rect.bottom + window.scrollY}px`;
        this.dropdown.style.left = `${rect.left + window.scrollX}px`;
        this.dropdown.style.width = `${rect.width}px`;
    }

    show() {
        this.dropdown.classList.add('pw-show');
        this.isVisible = true;
        this.updatePosition();
    }

    hide() {
        this.dropdown.classList.remove('pw-show');
        this.isVisible = false;
    }
}

window.PagePicker = PagePicker;
