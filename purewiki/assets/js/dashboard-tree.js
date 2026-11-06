/**
 * PureWiki - Dashboard Tree
 *
 * Sidebar tree view of the dashboard. Handles expanding, search,
 * collapsible sections, snippets and drag and drop
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

/** Initializes treeview interaction */
function initTreeview() {
    const toggles = document.querySelectorAll('.pw-tree-toggle');

    toggles.forEach(toggle => {
        toggle.addEventListener('click', (e) => {
            // Prevent the click from selecting the item
            e.stopPropagation();

            const node = toggle.closest('.pw-tree-node');
            if (node && node.classList.contains('pw-has-children')) {
                node.classList.toggle('pw-expanded');
            }
        });
    });
}

/** Initializes treeview search */
function initTreeSearch() {
    const input = document.getElementById('pw-tree-search-input');
    if (!input) return;

    const treeview = document.querySelector('.pw-treeview');
    if (!treeview) return;

    let debounceTimer = null;
    let savedExpandState = null;

    input.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => filterTree(input.value.trim()), 200);
    });

    /**
     * Filters treeview nodes based on given string
     * @param {string} query - The search term to filter
     */
    function filterTree(query) {
        const allNodes = treeview.querySelectorAll('.pw-tree-node');

        if (!query) {
            allNodes.forEach(node => node.classList.remove('pw-tree-hidden'));
            restoreExpandState();
            savedExpandState = null;
            ['pw-section-virtual-pages', 'pw-section-snippets'].forEach(secId => {
                const sec = document.getElementById(secId);
                if (sec) {
                    const key = secId === 'pw-section-virtual-pages' ? 'pw_sidebar_virtual_expanded' : 'pw_sidebar_snippets_expanded';
                    sec.classList.toggle('pw-expanded', localStorage.getItem(key) === 'true');
                }
            });
            return;
        }

        if (!savedExpandState) {
            saveExpandState();
        }

        const lowerQuery = query.toLowerCase();

        allNodes.forEach(node => node.classList.add('pw-tree-hidden'));

        allNodes.forEach(node => {
            const label = node.querySelector(':scope > .pw-tree-item .pw-tree-label');
            if (!label) return;

            if (label.textContent.toLowerCase().includes(lowerQuery)) {
                node.classList.remove('pw-tree-hidden');
                node.classList.add('pw-expanded');

                let parent = node.parentElement.closest('.pw-tree-node');
                while (parent) {
                    parent.classList.remove('pw-tree-hidden');
                    parent.classList.add('pw-expanded');
                    parent = parent.parentElement.closest('.pw-tree-node');
                }

                const section = node.closest('.pw-sidebar-section');
                if (section) {
                    section.classList.add('pw-expanded');
                }
            }
        });
    }

    /** Saves the current expand/collapse state of all tree nodes */
    function saveExpandState() {
        savedExpandState = new Map();
        treeview.querySelectorAll('.pw-tree-node').forEach(node => {
            savedExpandState.set(node, node.classList.contains('pw-expanded'));
        });
    }

    /** Restores the previously saved expand/collapse state of all tree nodes */
    function restoreExpandState() {
        if (!savedExpandState) return;
        savedExpandState.forEach((wasExpanded, node) => {
            node.classList.toggle('pw-expanded', wasExpanded);
        });
    }
}

/** Initializes collapsible sidebar sections */
function initCollapsibleSidebarSections() {
    const sections = [
        {
            id: 'pw-section-virtual-pages',
            headerId: 'pw-header-virtual-pages',
            storageKey: 'pw_sidebar_virtual_expanded'
        },
        {
            id: 'pw-section-snippets',
            headerId: 'pw-header-snippets',
            storageKey: 'pw_sidebar_snippets_expanded'
        }
    ];

    sections.forEach(({ id, headerId, storageKey }) => {
        const section = document.getElementById(id);
        const header = document.getElementById(headerId);
        if (!section || !header) return;

        // Load last state from localStorage
        const isExpanded = localStorage.getItem(storageKey) === 'true';
        section.classList.toggle('pw-expanded', isExpanded);

        const toggle = () => {
            const nowExpanded = section.classList.toggle('pw-expanded');
            try {
                localStorage.setItem(storageKey, nowExpanded ? 'true' : 'false');
            } catch (e) {}
        };

        header.addEventListener('click', (e) => {
            if (e.target.closest('#pw-btn-add-snippet')) return;
            toggle();
        });

        header.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                if (e.target.closest('#pw-btn-add-snippet')) return;
                e.preventDefault();
                toggle();
            }
        });
    });

    // Expand section if item inside is clicked
    const treeItems = document.querySelectorAll('.pw-sidebar-section .pw-tree-item');
    treeItems.forEach(item => {
        item.addEventListener('click', () => {
            const section = item.closest('.pw-sidebar-section');
            if (section && !section.classList.contains('pw-expanded')) {
                section.classList.add('pw-expanded');
                const key = section.id === 'pw-section-virtual-pages'
                    ? 'pw_sidebar_virtual_expanded'
                    : 'pw_sidebar_snippets_expanded';
                try {
                    localStorage.setItem(key, 'true');
                } catch (e) {}
            }
        });
    });
}

/** Initializes snippet create button logic */
function initSnippets() {
    const btnAddSnippet = document.getElementById('pw-btn-add-snippet');

    if (btnAddSnippet) {
        btnAddSnippet.addEventListener('click', async (e) => {
            e.stopPropagation();
            const newSnippetData = await openDialog({
                title: __('dashboard.add_snippet') || 'Add Snippet',
                text: __('dashboard.enter_snippet_title') || 'Enter a title for the new snippet:',
                type: 'prompt',
                placeholder: 'My Snippet'
            });

            if (newSnippetData && newSnippetData.value && newSnippetData.value.trim() !== '') {
                const title = newSnippetData.value.trim();
                const result = await apiSafe('create_snippet', { title });
                if (result) {
                    sessionStorage.setItem('pw-notify', JSON.stringify({text: __('dashboard.snippet_created'), type: 'success'}));
                    window.location.href = (window.PW_BASE_PATH || '') + '/dashboard/edit?path=' + encodeURIComponent(result.new_path);
                } else {
                    notify(__('dashboard.error_create_snippet'), 'error');
                }
            }
        });
    }
}

/** Restores active page state from sessionStorage on load */
function restoreActivePageState() {
    const savedPath = sessionStorage.getItem('pw_active_page');
    if (savedPath) {
        const targetItem = document.querySelector(`.pw-tree-item[data-path="${savedPath}"]`);
        if (targetItem) {
            // Expand all parent nodes
            let parentNode = targetItem.closest('.pw-tree-node');
            while (parentNode) {
                parentNode.classList.add('pw-expanded');
                parentNode = parentNode.parentElement.closest('.pw-tree-node');
            }
            const section = targetItem.closest('.pw-sidebar-section');
            if (section) {
                section.classList.add('pw-expanded');
            }
            // Trigger click to load content
            targetItem.click();
        }
    }
}

/** Initializes Drag and Drop functionality for treeview. */
function initTreeDragAndDrop() {
    const items = document.querySelectorAll('.pw-tree-item');
    let draggedPath = null;

    items.forEach(item => {
        // Prevent draggability for root and virtual system pages
        if (item.dataset.path !== '/' && !item.dataset.path.startsWith('/_')) {
            item.addEventListener('dragstart', (e) => {
                draggedPath = item.dataset.path;
                e.dataTransfer.effectAllowed = 'move';
                setTimeout(() => item.style.opacity = '0.5', 10);
            });

            item.addEventListener('dragend', () => {
                draggedPath = null;
                item.style.opacity = '1';
                clearDragClasses();
            });
        }

        item.addEventListener('dragover', (e) => {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';

            const targetPath = item.dataset.path;

            // Prevent dropping around virtual system pages
            if (targetPath && targetPath.startsWith('/_')) return;
            if (!draggedPath || draggedPath === targetPath) {
                clearDragClasses();
                return;
            }

            if (targetPath.startsWith(draggedPath + '/')) {
                clearDragClasses();
                return;
            }

            clearDragClasses();

            const rect = item.getBoundingClientRect();
            const y = e.clientY - rect.top;

            const threshold = rect.height * 0.35;

            if (targetPath === '/') {
                item.classList.add('pw-drop-inside');
                item.dataset.dropAction = 'inside';
            } else {
                if (y < threshold) {
                    item.classList.add('pw-drop-before');
                    item.dataset.dropAction = 'before';
                } else if (y > rect.height - threshold) {
                    item.classList.add('pw-drop-after');
                    item.dataset.dropAction = 'after';
                } else {
                    item.classList.add('pw-drop-inside');
                    item.dataset.dropAction = 'inside';
                }
            }
        });

        item.addEventListener('dragleave', (e) => {
            clearDragClasses();
        });

        item.addEventListener('drop', async (e) => {
            e.preventDefault();
            const dropAction = item.dataset.dropAction;
            const targetPath = item.dataset.path;

            clearDragClasses();

            if (!draggedPath || !dropAction || draggedPath === targetPath) {
                return;
            }

            if (targetPath.startsWith(draggedPath + '/')) {
                notify('Cannot move a folder into its own subfolder.', 'error');
                return;
            }

            const result = await apiSafe('drag_drop_page', {
                source_path: draggedPath,
                target_path: targetPath,
                position: dropAction
            });
            if (result) {
                sessionStorage.setItem('pw-notify', JSON.stringify({text: __('dashboard.page_moved'), type: 'success'}));
                window.location.reload();
            } else {
                notify(__('dashboard.error_move_page'), 'error');
            }
        });
    });

    function clearDragClasses() {
        items.forEach(el => {
            el.classList.remove('pw-drop-before', 'pw-drop-after', 'pw-drop-inside');
            delete el.dataset.dropAction;
        });
    }
}
