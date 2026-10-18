/**
 * PureWiki Theme - Default Script
 *
 * Navigation, theme toggle, TOC scroll spy, search and comments.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

document.addEventListener('DOMContentLoaded', () => {
    // Sticky Header Scroll Effect
    const header = document.querySelector('.pw-header');
    if (header) {
        const updateHeader = () => {
            header.classList.toggle('pw-header-scrolled', window.scrollY > 20);
        };
        window.addEventListener('scroll', updateHeader, { passive: true });
        updateHeader();
    }

    // Sidebar Toggle
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

        sidebarmenu.addEventListener('click', (e) => {
            e.stopPropagation();
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

    // Theme Toggle (auto > light > dark > auto)
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

        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)');
        const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

        function isDark(mode) {
            return mode === 'dark' || (mode === 'auto' && prefersDark.matches);
        }

        // Redraws the page as a circle growing from the toggle button
        function applyThemeAnimated(mode) {
            const rect = themeBtn.getBoundingClientRect();
            const x = rect.left + rect.width / 2;
            const y = rect.top + rect.height / 2;
            const radius = Math.hypot(Math.max(x, window.innerWidth - x), Math.max(y, window.innerHeight - y));

            // Running CSS transitions would bleed into the new snapshot
            document.documentElement.classList.add('pw-theme-switching');
            const transition = document.startViewTransition(() => applyTheme(mode));

            transition.ready.then(() => {
                document.documentElement.animate(
                    { clipPath: [`circle(0 at ${x}px ${y}px)`, `circle(${radius}px at ${x}px ${y}px)`] },
                    { duration: 500, easing: 'ease-in-out', pseudoElement: '::view-transition-new(root)' }
                );
            }).catch(() => {});

            // Timeout as safety net in case the transition is skipped, e.g. in a background tab
            const done = () => document.documentElement.classList.remove('pw-theme-switching');
            transition.finished.finally(done).catch(() => {});
            setTimeout(done, 1000);
        }

        themeBtn.addEventListener('click', () => {
            const current = getCurrentMode();
            const next = MODES[(MODES.indexOf(current) + 1) % MODES.length];

            // Skip the effect if the look does not change, e.g. auto > dark on a dark system
            const animate = document.startViewTransition && !prefersReducedMotion.matches && isDark(current) !== isDark(next);
            if (animate) {
                applyThemeAnimated(next);
            } else {
                applyTheme(next);
            }
        });
    }

    // TOC Scroll Spy
    const tocLinks = document.querySelectorAll('.pw-toc-container a[href^="#"]');
    if (tocLinks.length > 0) {
        const headingIds = [];
        tocLinks.forEach(link => {
            const href = link.getAttribute('href');
            if (href && href.length > 1) {
                const id = href.slice(1);
                if (id) headingIds.push(id);
            }
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

        function updateActiveToc() {
            let currentId = null;
            for (let i = 0; i < headingIds.length; i++) {
                const el = document.getElementById(headingIds[i]);
                if (el) {
                    const rect = el.getBoundingClientRect();
                    if (rect.top <= 140) {
                        currentId = headingIds[i];
                    } else {
                        break;
                    }
                }
            }
            if (!currentId && headingIds.length > 0) {
                currentId = headingIds[0];
            }

            // Last headings can never reach the offset on short final sections
            const atPageEnd = window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 2;
            if (atPageEnd && headingIds.length > 0) {
                for (let i = headingIds.length - 1; i >= 0; i--) {
                    if (document.getElementById(headingIds[i])) {
                        currentId = headingIds[i];
                        break;
                    }
                }
            }
            if (currentId) {
                setActiveToc(currentId);
            }
        }

        window.addEventListener('scroll', updateActiveToc, { passive: true });
        updateActiveToc();
    }

    // Pagelist Toggle
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

    // Code Copy to Clipboard
    document.querySelectorAll('.pw-code-copy').forEach(btn => {
        btn.addEventListener('click', async () => {
            const wrapper = btn.closest('.pw-code-wrapper');
            const codeEl = wrapper ? wrapper.querySelector('pre code') : null;
            if (!codeEl) return;
            const code = codeEl.innerText;
            try {
                await navigator.clipboard.writeText(code);
                const icon = btn.querySelector('iconify-icon');
                const originalIcon = icon ? icon.getAttribute('icon') : null;
                btn.classList.add('success');
                if (icon) icon.setAttribute('icon', 'mdi:check');
                setTimeout(() => {
                    btn.classList.remove('success');
                    if (icon && originalIcon) icon.setAttribute('icon', originalIcon);
                }, 2000);
            } catch (err) {
                console.error('Failed to copy:', err);
            }
        });
    });

    // To Top Button
    const toTopBtn = document.getElementById('pw-to-top');
    if (toTopBtn) {
        const handleScroll = () => {
            if (window.scrollY > 300) {
                toTopBtn.classList.add('pw-visible');
            } else {
                toTopBtn.classList.remove('pw-visible');
            }
        };
        window.addEventListener('scroll', handleScroll, { passive: true });
        handleScroll();

        toTopBtn.addEventListener('click', () => {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    }

    // Search
    const searchToggle = document.getElementById('pw-search-toggle');
    const searchDropdown = document.getElementById('pw-search-dropdown');
    const searchInput = document.getElementById('pw-search-input');
    const searchResults = document.getElementById('pw-search-results');

    if (searchToggle && searchDropdown && searchInput && searchResults) {
        let searchTimer = null;
        let selectedIndex = -1;

        function updateSelection(items) {
            items.forEach((item, idx) => {
                if (idx === selectedIndex) {
                    item.classList.add('pw-active');
                    item.scrollIntoView({ block: 'nearest' });
                } else {
                    item.classList.remove('pw-active');
                }
            });
        }

        function toggleSearch(open) {
            searchDropdown.classList.toggle('pw-open', open);
            selectedIndex = -1;
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
            if (!searchDropdown.contains(e.target) && e.target !== searchToggle && !searchToggle.contains(e.target)) {
                toggleSearch(false);
            }
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && searchDropdown.classList.contains('pw-open')) {
                toggleSearch(false);
            }
        });

        searchInput.addEventListener('keydown', (e) => {
            const items = searchResults.querySelectorAll('.pw-search-result-item');
            if (!items.length) return;

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                selectedIndex = (selectedIndex + 1) % items.length;
                updateSelection(items);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                selectedIndex = selectedIndex <= 0 ? items.length - 1 : selectedIndex - 1;
                updateSelection(items);
            } else if (e.key === 'Enter') {
                e.preventDefault();
                const targetIndex = selectedIndex >= 0 ? selectedIndex : 0;
                if (items[targetIndex]) {
                    items[targetIndex].click();
                }
            }
        });

        searchInput.addEventListener('input', () => {
            clearTimeout(searchTimer);
            selectedIndex = -1;
            const q = searchInput.value.trim();
            if (!q) {
                searchResults.innerHTML = '';
                return;
            }
            searchTimer = setTimeout(() => performSearch(q), 300);
        });

        async function performSearch(query) {
            try {
                const lang = window.PW_CURRENT_LANG || '';
                const res = await fetch((window.PW_BASE_PATH || '') + '/purewiki/api.php?action=search&q=' + encodeURIComponent(query) + '&lang=' + encodeURIComponent(lang));
                const data = await res.json();
                selectedIndex = -1;
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

                        const items = searchResults.querySelectorAll('.pw-search-result-item');
                        items.forEach((item, idx) => {
                            item.addEventListener('mouseenter', () => {
                                selectedIndex = idx;
                                updateSelection(items);
                            });
                        });
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

    // Comments Form
    const commentForm = document.getElementById('pw-comment-form');
    if (commentForm) {
        commentForm.addEventListener('submit', async function(e) {
            e.preventDefault();

            const submitBtn = document.getElementById('pw-comment-submit');
            const msgBox = document.getElementById('pw-comment-message');
            
            if (submitBtn) submitBtn.disabled = true;

            const formData = new FormData(commentForm);
            const apiEndpoint = commentForm.getAttribute('data-api-endpoint');
            const requireApproval = commentForm.getAttribute('data-require-approval') === 'true';
            const pendingMessage = commentForm.getAttribute('data-pending-message');
            const successMessage = commentForm.getAttribute('data-success-message');

            try {
                const response = await fetch(apiEndpoint, {
                    method: 'POST',
                    body: formData
                });

                if (!response.ok) {
                    throw new Error('Network response error');
                }

                const result = await response.json();

                if (result && result.success) {
                    msgBox.className = 'pw-comment-message pw-comment-message-success';
                    msgBox.style.display = 'block';
                    
                    if (requireApproval) {
                        msgBox.textContent = pendingMessage;
                    } else {
                        msgBox.textContent = successMessage;
                        setTimeout(() => window.location.reload(), 1500);
                    }

                    const nameInput = document.getElementById('pw-comment-name');
                    const emailInput = document.getElementById('pw-comment-email');
                    const textInput = document.getElementById('pw-comment-text');
                    if (nameInput) nameInput.value = '';
                    if (emailInput) emailInput.value = '';
                    if (textInput) textInput.value = '';
                } else {
                    throw new Error(result.message || 'Unknown error');
                }
            } catch (error) {
                msgBox.className = 'pw-comment-message pw-comment-message-error';
                msgBox.style.display = 'block';
                msgBox.textContent = error.message || 'An error occurred. Please try again.';
            } finally {
                if (submitBtn) submitBtn.disabled = false;
            }
        });
    }
});
