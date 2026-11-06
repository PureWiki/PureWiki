/**
 * PureWiki - Dashboard
 *
 * Main area of the dashboard. Shows page details for the selected
 * tree item and handles add, duplicate and delete actions
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

let currentSelectedPath = null;
let currentSelectedName = null;

/** Initializes dashboard main interactions */
function initDashboardInteractions() {
    const treeItems = document.querySelectorAll('.pw-tree-item');
    const mainActions = document.getElementById('pw-main-actions');
    const mainContent = document.getElementById('pw-main-content');

    const btnAdd = document.getElementById('pw-btn-add-subpage');
    const btnDelete = document.getElementById('pw-btn-delete-page');
    const btnEdit = document.getElementById('pw-btn-edit-page');
    const btnSettings = document.getElementById('pw-btn-page-settings-dash');
    const btnDuplicate = document.getElementById('pw-btn-duplicate-page');

    if (btnEdit) {
        btnEdit.addEventListener('click', () => {
            if (!currentSelectedPath) return;
            if (typeof openEditorForPage === 'function') {
                openEditorForPage(currentSelectedPath, currentSelectedName);
            } else {
                window.location.href = (window.PW_BASE_PATH || '') + '/dashboard/edit?path=' + encodeURIComponent(currentSelectedPath);
            }
        });
    }

    if (btnSettings) {
        btnSettings.addEventListener('click', () => {
            if (!currentSelectedPath) return;
            if (typeof openEditorForPage === 'function') {
                openEditorForPage(currentSelectedPath, currentSelectedName, true);
            } else {
                window.location.href = (window.PW_BASE_PATH || '') + '/dashboard/page-settings?path=' + encodeURIComponent(currentSelectedPath) + '&from=dashboard';
            }
        });
    }

    bindSidebarTreeItems(treeItems, mainActions, mainContent, btnAdd, btnDelete, btnDuplicate, btnSettings);
    bindAddSubPageAction(btnAdd);
    bindDuplicatePageAction(btnDuplicate);
    bindDeletePageAction(btnDelete);
    restoreActivePageState();
}

/** Sidebar Click Logic */
function bindSidebarTreeItems(treeItems, mainActions, mainContent, btnAdd, btnDelete, btnDuplicate, btnSettings) {
    treeItems.forEach(item => {
        item.addEventListener('click', () => {
            treeItems.forEach(el => el.classList.remove('pw-tree-active'));

            item.classList.add('pw-tree-active');

            const path = item.getAttribute('data-path');
            const label = item.querySelector('.pw-tree-label').textContent;

            currentSelectedPath = path;
            currentSelectedName = label;
            sessionStorage.setItem('pw_active_page', path);

            if (mainActions) mainActions.style.display = 'flex';

            const isSystemPage = path.startsWith('/_virtual/');
            const isSnippet = path.startsWith('/_snippets/');

            // Configure primary button visibility based on system page rules
            if (path === '/') {
                if (btnDelete) btnDelete.style.display = 'none';
                if (btnDuplicate) btnDuplicate.style.display = 'none';
                if (btnAdd) btnAdd.style.display = 'inline-flex';
                if (btnSettings) btnSettings.style.display = 'inline-flex';
            } else if (isSystemPage) {
                if (btnDelete) btnDelete.style.display = 'none';
                if (btnDuplicate) btnDuplicate.style.display = 'none';
                if (btnAdd) btnAdd.style.display = 'none';
                if (btnSettings) btnSettings.style.display = 'none';
            } else if (isSnippet) {
                if (btnDelete) btnDelete.style.display = 'inline-flex';
                if (btnDuplicate) btnDuplicate.style.display = 'none';
                if (btnAdd) btnAdd.style.display = 'none';
                if (btnSettings) btnSettings.style.display = 'none';
            } else {
                if (btnDelete) btnDelete.style.display = 'inline-flex';
                if (btnDuplicate) btnDuplicate.style.display = 'inline-flex';
                if (btnAdd) btnAdd.style.display = 'inline-flex';
                if (btnSettings) btnSettings.style.display = 'inline-flex';
            }

            // Update action button texts if snippet is selected
            const btnEdit = document.getElementById('pw-btn-edit-page');
            if (btnEdit) {
                btnEdit.innerHTML = isSnippet
                    ? '<iconify-icon icon="mdi:pencil"></iconify-icon> ' + (__('dashboard.edit_snippet') || 'Edit Snippet')
                    : '<iconify-icon icon="mdi:pencil"></iconify-icon> ' + (__('dashboard.edit_page') || 'Edit Page');
            }

            if (btnDelete) {
                btnDelete.innerHTML = isSnippet
                    ? '<iconify-icon icon="mdi:delete"></iconify-icon> ' + (__('dashboard.delete_snippet') || 'Delete Snippet')
                    : '<iconify-icon icon="mdi:delete"></iconify-icon> ' + (__('dashboard.delete_page') || 'Delete Page');
            }

            resetDeleteButton(btnDelete, isSnippet);

            if (mainContent) {
                const headerTpl = document.getElementById('tpl-page-header');
                mainContent.innerHTML = '';
                if (headerTpl) {
                    const header = headerTpl.content.cloneNode(true);
                    header.querySelector('[data-field="label"]').textContent = label;
                    header.querySelector('[data-field="path"]').textContent = path;
                    mainContent.appendChild(header);
                }
                const loadingP = document.createElement('p');
                loadingP.textContent = __('common.loading_details');
                mainContent.appendChild(loadingP);
                apiCall('get_page', { path })
                .then(result => {
                    if (loadingP.parentNode) loadingP.remove();

                    if (result.success && result.data) {
                        const d = result.data;

                        const columnsContainer = document.createElement('div');
                        columnsContainer.className = 'pw-dashboard-columns';

                        const colLeft = document.createElement('div');
                        colLeft.className = 'pw-dashboard-column';

                        const colRight = document.createElement('div');
                        colRight.className = 'pw-dashboard-column';

                        columnsContainer.appendChild(colLeft);
                        columnsContainer.appendChild(colRight);
                        mainContent.appendChild(columnsContainer);

                        renderPageInfoCard(colLeft, result);

                        if (path === '/') {
                            renderRecentPagesCard(colRight);
                        }

                        if (path !== '/' && !isSystemPage && !isSnippet) {
                            renderRenameCard(colRight, path);
                            renderRedirectCard(colRight, path, d);
                            renderMoveCard(colRight);
                        }

                        if (!isSystemPage && !isSnippet) {
                            renderBacklinksCard(colLeft, path);
                            renderCommentsCard(colLeft, path);
                        }

                        // Remove empty column if no card
                        if (colRight.children.length === 0) {
                            colRight.remove();
                        }
                    } else {
                        const fallback = document.createElement('p');
                        fallback.textContent = __('common.use_buttons');
                        mainContent.appendChild(fallback);
                    }

                    const btnMoveUp = document.getElementById('pw-btn-move-up');
                    const btnMoveDown = document.getElementById('pw-btn-move-down');

                    const handleMove = async (dir) => {
                        const result = await apiSafe('move_page', { path, direction: dir });
                        if (result) {
                            sessionStorage.setItem('pw-notify', JSON.stringify({text: __('dashboard.page_moved'), type: 'success'}));
                            window.location.reload();
                        }
                    };

                    if (btnMoveUp) btnMoveUp.addEventListener('click', () => handleMove('up'));
                    if (btnMoveDown) btnMoveDown.addEventListener('click', () => handleMove('down'));




                })
                .catch(err => {
                    if (loadingP.parentNode) loadingP.remove();
                    const errP = document.createElement('p');
                    errP.textContent = __('common.failed_load_details');
                    mainContent.appendChild(errP);
                    console.error(err);
                });
            }

            // Reset delete button
            resetDeleteButton(btnDelete, isSnippet);
        });
    });
}

/** Add Sub-Page Logic */
function bindAddSubPageAction(btnAdd) {
    if (!btnAdd) return;

    btnAdd.addEventListener('click', async () => {
        if (!currentSelectedPath) return;

        const newPageData = await openDialog({
            title: __('dashboard.create_subpage'),
            text: __('dashboard.enter_title'),
            type: 'prompt',
            placeholder: __('dashboard.enter_title_placeholder'),
            showLayout: true
        });

        if (newPageData && newPageData.value && newPageData.value.trim() !== '') {
            const title = newPageData.value.trim();
            const layout = newPageData.layout || 'page';
            const parentPath = currentSelectedPath;

            const result = await apiSafe('create_page', { title, parent_path: parentPath, layout });
            if (result) {
                sessionStorage.setItem('pw-notify', JSON.stringify({text: __('dashboard.page_created'), type: 'success'}));
                window.location.href = (window.PW_BASE_PATH || '') + '/dashboard/edit?path=' + encodeURIComponent(result.new_path);
            }
        }
    });
}

/** Duplicate Page Logic */
function bindDuplicatePageAction(btnDuplicate) {
    if (!btnDuplicate) return;

    btnDuplicate.addEventListener('click', async () => {
        if (!currentSelectedPath) return;

        const newPageData = await openDialog({
            title: __('dashboard.duplicate_title'),
            text: __('dashboard.enter_duplicate_title', currentSelectedName),
            type: 'prompt',
            placeholder: currentSelectedName + ' Copy'
        });

        if (newPageData && newPageData.value && newPageData.value.trim() !== '') {
            const result = await apiSafe('duplicate_page', {
                path: currentSelectedPath,
                title: newPageData.value.trim()
            });
            if (result) {
                if (result.new_path) {
                    sessionStorage.setItem('pw_active_page', result.new_path);
                }
                sessionStorage.setItem('pw-notify', JSON.stringify({text: __('dashboard.page_duplicated'), type: 'success'}));
                window.location.reload();
            } else {
                notify(__('dashboard.error_duplicate_page'), 'error');
            }
        }
    });
}

/** Delete Page Logic */
function bindDeletePageAction(btnDelete) {
    if (!btnDelete) return;

    let isDeleteConfirm = false;

    const handleDeleteClick = async (e) => {
        e.stopPropagation(); // prevent from resetting immediately

        if (!currentSelectedPath) return;

        if (currentSelectedPath === '/') {
            await openDialog({ title: __('dashboard.action_denied'), text: __('dashboard.cannot_delete_root'), type: 'alert' });
            return;
        }

        // Check for backlinks
        if (!currentSelectedPath.startsWith('/_snippets/')) {
            const backlinkRes = await apiCall('get_backlinks', { path: currentSelectedPath });
            if (backlinkRes && backlinkRes.enabled !== false && backlinkRes.success && Array.isArray(backlinkRes.backlinks) && backlinkRes.backlinks.length > 0) {
                const linksListHtml = backlinkRes.backlinks.map(b =>
                    `<li style="margin-bottom: 6px;"><strong>${escapeHtml(b.title || b.path)}</strong> <span style="color: var(--pw-text-muted); font-size: 0.8em;">(${escapeHtml(b.path)})</span></li>`
                ).join('');

                const warningHtml = `
                    <div style="margin-bottom: 12px; color: var(--pw-danger, #e53e3e); font-weight: 500; display: flex; align-items: center; gap: 6px;">
                        <iconify-icon icon="mdi:alert-circle-outline" style="font-size: 1.3em; flex-shrink: 0;"></iconify-icon>
                        <span>${escapeHtml(__('dashboard.delete_page_has_backlinks_desc'))}</span>
                    </div>
                    <ul style="max-height: 160px; overflow-y: auto; margin-bottom: 15px; border: 1px solid var(--pw-border); border-radius: var(--pw-radius, 4px); padding: 10px 14px 10px 30px; background: var(--pw-surface-light, rgba(255,255,255,0.02));">
                        ${linksListHtml}
                    </ul>
                    <p style="margin: 0; font-size: 0.85em; color: var(--pw-text-muted);">${escapeHtml(__('dashboard.confirm_delete_anyway'))}</p>
                `;

                const confirmed = await openDialog({
                    title: __('dashboard.delete_page_has_backlinks_title'),
                    html: warningHtml,
                    type: 'confirm',
                    confirmText: __('dashboard.delete_anyway') || __('common.delete'),
                    cancelText: __('common.cancel')
                });

                if (!confirmed) {
                    return;
                }

                const result = await apiSafe('delete_page', { path: currentSelectedPath });
                if (result) {
                    sessionStorage.setItem('pw-notify', JSON.stringify({text: __('dashboard.page_deleted'), type: 'success'}));
                    window.location.reload();
                } else {
                    notify(__('dashboard.error_delete_page'), 'error');
                }
                return;
            }
        }

        if (!isDeleteConfirm) {
            isDeleteConfirm = true;
            btnDelete.textContent = __('common.confirm');
            btnDelete.classList.add('pw-confirm-state');
        } else {
            const endpoint = currentSelectedPath.startsWith('/_snippets/') ? 'delete_snippet' : 'delete_page';
            const result = await apiSafe(endpoint, { path: currentSelectedPath });
            if (result) {
                const isSnippet = currentSelectedPath && currentSelectedPath.startsWith('/_snippets/');
                sessionStorage.setItem('pw-notify', JSON.stringify({text: isSnippet ? __('dashboard.snippet_deleted') : __('dashboard.page_deleted'), type: 'success'}));
                window.location.reload();
            } else {
                const isSnippet = currentSelectedPath && currentSelectedPath.startsWith('/_snippets/');
                notify(isSnippet ? __('dashboard.error_delete_snippet') : __('dashboard.error_delete_page'), 'error');
                resetDeleteButton(btnDelete, isSnippet);
                isDeleteConfirm = false;
            }
        }
    };

    btnDelete.addEventListener('click', handleDeleteClick);

    // Click anywhere resets delete button
    window.addEventListener('click', (e) => {
        if (isDeleteConfirm && e.target !== btnDelete && !btnDelete.contains(e.target)) {
            const isSnippet = currentSelectedPath && currentSelectedPath.startsWith('/_snippets/');
            resetDeleteButton(btnDelete, isSnippet);
            isDeleteConfirm = false;
        }
    });
}

/** Resets the delete button */
function resetDeleteButton(btn, isSnippet = false) {
    if (!btn) return;
    btn.innerHTML = isSnippet
        ? '<iconify-icon icon="mdi:delete"></iconify-icon> ' + (__('dashboard.delete_snippet') || 'Delete Snippet')
        : '<iconify-icon icon="mdi:delete"></iconify-icon> ' + (__('dashboard.delete_page') || 'Delete Page');
    btn.classList.remove('pw-confirm-state');
}
