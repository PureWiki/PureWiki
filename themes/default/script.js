/**
 * PureWiki Theme - Frontend Logic
 *
 * Core JavaScript for the default theme. Handles responsive navigation,
 * theme switching, TOC scroll animation, and search interactions
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

document.addEventListener('DOMContentLoaded', () => {
    //Sticky Header Scroll Effect
    const header = document.querySelector('.pw-header');
    if (header) {
        const updateHeader = () => {
            header.classList.toggle('pw-header-scrolled', window.scrollY > 20);
        };
        window.addEventListener('scroll', updateHeader, { passive: true });
        updateHeader(); // Initial check
    }

    //Sidebar Toggle
    const sidebarmenu = document.getElementById('pw-sidebarmenu');
    const sidebar = document.getElementById('pw-sidebar-left');
    const backdrop = document.getElementById('pw-sidebar-backdrop');

    if (sidebarmenu && sidebar && backdrop) {
        function toggleSidebar(open) {
            sidebar.classList.toggle('pw-sidebar-open', open);
            backdrop.classList.toggle('pw-sidebar-open', open);
            sidebarmenu.classList.toggle('pw-sidebarmenu-active', open);
            document.body.classList.toggle('pw-sidebar-noscroll', open);
        }

        sidebarmenu.addEventListener('click', () => {
            const isOpen = sidebar.classList.contains('pw-sidebar-open');
            toggleSidebar(!isOpen);
        });

        backdrop.addEventListener('click', () => {
            toggleSidebar(false);
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && sidebar.classList.contains('pw-sidebar-open')) {
                toggleSidebar(false);
            }
        });
    }

    //Theme Toggle (auto → light → dark → auto)
    const themeBtn = document.getElementById('pw-theme-toggle');
    if (themeBtn) {
        const MODES = ['auto', 'light', 'dark'];

        function getCurrentMode() {
            return localStorage.getItem('pw-theme') || 'auto';
        }

        function applyTheme(mode) {
            if (mode === 'auto') {
                document.documentElement.removeAttribute('data-theme');
                localStorage.removeItem('pw-theme');
            } else {
                document.documentElement.setAttribute('data-theme', mode);
                localStorage.setItem('pw-theme', mode);
            }
        }

        themeBtn.addEventListener('click', () => {
            const current = getCurrentMode();
            const nextIndex = (MODES.indexOf(current) + 1) % MODES.length;
            applyTheme(MODES[nextIndex]);
        });
    }

    //TOC Scroll Spy
    const tocLinks = document.querySelectorAll('.pw-toc-container a[href^="#"]');
    if (tocLinks.length > 0) {
        const headingIds = [];
        tocLinks.forEach(link => {
            const id = link.getAttribute('href').slice(1);
            if (id) headingIds.push(id);
        });

        function setActiveToc(id) {
            tocLinks.forEach(link => {
                const li = link.closest('li');
                if (li) li.classList.remove('pw-toc-active');
            });
            const activeLinks = document.querySelectorAll('.pw-toc-container a[href="#' + id + '"]');
            activeLinks.forEach(activeLink => {
                const li = activeLink.closest('li');
                if (li) li.classList.add('pw-toc-active');
            });
        }

        const observer = new IntersectionObserver(() => {
            let bestId = null;
            let minTop = Infinity;

            headingIds.forEach(id => {
                const el = document.getElementById(id);
                if (el) {
                    const rect = el.getBoundingClientRect();
                    if (rect.top >= 105 && rect.top < window.innerHeight * 0.4) {
                        if (rect.top < minTop) {
                            minTop = rect.top;
                            bestId = id;
                        }
                    }
                }
            });

            if (bestId) {
                setActiveToc(bestId);
            }
        }, {
            // Margin accounts for the sticky header
            rootMargin: '-100px 0px -50% 0px',
            threshold: 0
        });

        headingIds.forEach(id => {
            const el = document.getElementById(id);
            if (el) observer.observe(el);
        });
    }

    //Pagelist Toggle
    document.querySelectorAll('.pw-pagelist .pw-toggle').forEach(toggle => {
        toggle.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            const li = toggle.closest('li');
            if (li) {
                if (li.classList.contains('active') || li.classList.contains('open')) {
                    li.classList.toggle('js-collapsed');
                } else {
                    li.classList.toggle('js-expanded');
                }
            }
        });
    });

    //Code Copy to Clipboard
    document.querySelectorAll('.pw-code-copy').forEach(btn => {
        btn.addEventListener('click', async () => {
            const wrapper = btn.closest('.pw-code-wrapper');
            const code = wrapper.querySelector('pre code').innerText;
            try {
                await navigator.clipboard.writeText(code);
                const icon = btn.querySelector('iconify-icon');
                const originalIcon = icon.getAttribute('icon');
                btn.classList.add('success');
                icon.setAttribute('icon', 'mdi:check');
                setTimeout(() => {
                    btn.classList.remove('success');
                    icon.setAttribute('icon', originalIcon);
                }, 2000);
            } catch (err) {
                console.error('Failed to copy:', err);
            }
        });
    });

    //To Top Button Logic
    const toTopBtn = document.getElementById('pw-to-top');
    if (toTopBtn) {
        window.addEventListener('scroll', () => {
            if (window.scrollY > 300) {
                toTopBtn.classList.add('pw-visible');
            } else {
                toTopBtn.classList.remove('pw-visible');
            }
        });

        toTopBtn.addEventListener('click', () => {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    }
    //Search
    const searchToggle = document.getElementById('pw-search-toggle');
    const searchDropdown = document.getElementById('pw-search-dropdown');
    const searchInput = document.getElementById('pw-search-input');
    const searchResults = document.getElementById('pw-search-results');

    if (searchToggle && searchDropdown && searchInput && searchResults) {
        let searchTimer = null;

        function toggleSearch(open) {
            searchDropdown.classList.toggle('pw-open', open);
            if (open) {
                setTimeout(() => searchInput.focus(), 50);
            } else {
                searchInput.value = '';
                searchResults.innerHTML = '';
            }
        }

        searchToggle.addEventListener('click', (e) => {
            e.stopPropagation();
            toggleSearch(!searchDropdown.classList.contains('pw-open'));
        });

        document.addEventListener('click', (e) => {
            if (!searchDropdown.contains(e.target) && e.target !== searchToggle) {
                toggleSearch(false);
            }
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && searchDropdown.classList.contains('pw-open')) {
                toggleSearch(false);
            }
        });

        searchInput.addEventListener('input', () => {
            clearTimeout(searchTimer);
            const q = searchInput.value.trim();
            if (!q) {
                searchResults.innerHTML = '';
                return;
            }
            searchTimer = setTimeout(() => performSearch(q), 300);
        });

        async function performSearch(query) {
            try {
                const res = await fetch((window.PW_BASE_PATH || '') + '/purewiki/api.php?action=search&q=' + encodeURIComponent(query));
                const data = await res.json();
                if (data.success && data.results) {
                    if (data.results.length === 0) {
                        searchResults.innerHTML = '<div class="pw-search-empty">No results found.</div>';
                    } else {
                        searchResults.innerHTML = data.results.map(r =>
                            `<a href="${r.path}" class="pw-search-result-item">
                                <span class="pw-search-result-title">${escapeHtml(r.title)}</span>
                                <span class="pw-search-result-excerpt">${r.excerpt}</span>
                            </a>`
                        ).join('');
                    }
                }
            } catch (e) {
                searchResults.innerHTML = '<div class="pw-search-empty">Search failed.</div>';
            }
        }

        function escapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }
    }
});
