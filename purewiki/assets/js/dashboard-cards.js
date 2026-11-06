/**
 * PureWiki - Dashboard Cards
 *
 * Cards shown in the main area of the dashboard for the selected page,
 * like page info, rename, redirect, backlinks and comments
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

/** Adds the page info card to the left column. */
function renderPageInfoCard(colLeft, result) {
    const d = result.data;
    const created = d.DateCreated ? new Date(d.DateCreated).toLocaleString() : __('common.na');
    const modified = d.DateModified ? new Date(d.DateModified).toLocaleString() : __('common.na');
    const desc = d.description || d.Description || __('common.no_description');

    const infoTpl = document.getElementById('tpl-page-info');
    if (infoTpl) {
        const info = infoTpl.content.cloneNode(true);
        info.querySelector('[data-field="author"]').textContent = d.Author || '—';
        info.querySelector('[data-field="created"]').textContent = created;
        info.querySelector('[data-field="modified"]').textContent = modified;
        info.querySelector('[data-field="description"]').textContent = desc;
        info.querySelector('[data-field="tags"]').textContent = (Array.isArray(d.Tags) && d.Tags.length > 0) ? d.Tags.join(', ') : '—';
        const translations = (Array.isArray(result.available_langs) ? result.available_langs : []).filter(l => l !== '');
        info.querySelector('[data-field="translations"]').textContent = translations.length > 0 ? translations.join(', ') : '—';
        if (result.is_draft) {
            info.querySelector('[data-field="draft-badge"]').style.display = 'inline-block';
        }
        if (d.isPrivate) {
            info.querySelector('[data-field="private-badge"]').style.display = 'inline-block';
        }
        colLeft.appendChild(info);
    }
}

/** Adds the recently modified pages card to the right column. */
function renderRecentPagesCard(colRight) {
    const recentTpl = document.getElementById('tpl-recent-pages-card');
    if (recentTpl) {
        const recentCard = recentTpl.content.cloneNode(true);
        const listContainer = recentCard.querySelector('[data-field="recent-pages-list"]');
        const noPagesMsg = recentCard.querySelector('[data-field="no-recent-pages"]');

        colRight.appendChild(recentCard);

        apiSafe('list_recent_pages', { limit: 10 })
        .then(res => {
            if (res && res.success && Array.isArray(res.data) && res.data.length > 0) {
                const pages = res.data;
                listContainer.innerHTML = '';
                pages.forEach(p => {
                    const row = document.createElement('div');
                    row.className = 'pw-recent-page-item';

                    const left = document.createElement('div');
                    left.style.minWidth = '0';

                    let badgesHtml = '';
                    if (p.is_draft) {
                        badgesHtml += `<span class="pw-draft-badge" style="font-size:9px;margin-left:6px;">${__('dashboard.draft')}</span>`;
                    }
                    if (p.is_private) {
                        badgesHtml += `<span class="pw-private-badge" style="font-size:9px;margin-left:6px;">${__('dashboard.private')}</span>`;
                    }

                    left.innerHTML = `
                        <div><span class="pw-recent-page-title">${escapeHtml(p.title || p.path)}</span>${badgesHtml}</div>
                        <div class="pw-recent-page-path">${escapeHtml(p.path)}</div>
                    `;

                    const right = document.createElement('div');
                    right.className = 'pw-recent-page-meta';

                    const dateStr = p.modified ? formatPwDate(new Date(p.modified)) : '—';
                    const authorStr = p.author ? ` (${escapeHtml(p.author)})` : '';
                    right.innerHTML = `<span>${escapeHtml(dateStr)}${authorStr}</span>`;

                    const editBtn = document.createElement('button');
                    editBtn.className = 'pw-btn pw-recent-page-action-btn';
                    editBtn.title = __('dashboard.edit_page') || 'Edit Page';
                    editBtn.innerHTML = '<iconify-icon icon="mdi:pencil"></iconify-icon>';
                    editBtn.onclick = (e) => {
                        e.stopPropagation();
                        window.location.href = window.PW_BASE_PATH + '/dashboard/edit?path=' + encodeURIComponent(p.path);
                    };
                    right.appendChild(editBtn);

                    if (p.is_published) {
                        const viewBtn = document.createElement('button');
                        viewBtn.className = 'pw-btn pw-recent-page-action-btn';
                        viewBtn.title = __('dashboard.view_live_page') || 'Open Live Page';
                        viewBtn.innerHTML = '<iconify-icon icon="mdi:open-in-new"></iconify-icon>';
                        viewBtn.onclick = (e) => {
                            e.stopPropagation();
                            const liveUrl = (window.PW_BASE_PATH || '') + (p.path === '/' ? '/' : p.path);
                            window.open(liveUrl, '_blank');
                        };
                        right.appendChild(viewBtn);
                    }

                    row.appendChild(left);
                    row.appendChild(right);

                    row.onclick = () => {
                        const treeItem = document.querySelector(`.pw-tree-item[data-path="${p.path}"]`);
                        if (treeItem) {
                            treeItem.click();
                            treeItem.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                        }
                    };

                    listContainer.appendChild(row);
                });
            } else {
                noPagesMsg.style.display = 'block';
            }
        });
    }
}

/** Adds the rename card to the right column. */
function renderRenameCard(colRight, path) {
    const renameTpl = document.getElementById('tpl-rename-card');
    if (renameTpl) {
        const pathParts = path.split('/').filter(Boolean);
        const currentName = pathParts.pop();
        const parentPath = pathParts.length > 0 ? '/' + pathParts.join('/') + '/' : '/';

        const rename = renameTpl.content.cloneNode(true);
        const parentSpan = rename.querySelector('[data-field="parent-path"]');
        const renameInput = rename.querySelector('#pw-input-rename-folder');
        const btnRename = rename.querySelector('#pw-btn-rename-folder');

        if (parentSpan) parentSpan.textContent = parentPath;
        if (renameInput) renameInput.value = currentName;
        if (btnRename) btnRename.disabled = true;

        const checkRenameChanged = () => {
            if (!renameInput || !btnRename) return;
            const val = renameInput.value.trim();
            btnRename.disabled = (val === '' || val === currentName);
        };

        if (renameInput) {
            renameInput.addEventListener('input', checkRenameChanged);
            renameInput.addEventListener('change', checkRenameChanged);
            renameInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && btnRename && !btnRename.disabled) {
                    e.preventDefault();
                    btnRename.click();
                }
            });
        }

        if (btnRename) {
            btnRename.addEventListener('click', async () => {
                if (btnRename.disabled) return;
                const newName = renameInput.value.trim();
                if (!newName || newName === currentName) {
                    btnRename.disabled = true;
                    return;
                }

                if (newName.startsWith('_')) {
                    notify(__('dashboard.error_no_underscore'), 'error');
                    return;
                }

                btnRename.disabled = true;
                const result = await apiSafe('rename_folder', { path, new_name: newName });
                if (result && result.new_path) {
                    sessionStorage.setItem('pw_active_page', result.new_path);
                    sessionStorage.setItem('pw-notify', JSON.stringify({ text: __('dashboard.folder_renamed'), type: 'success' }));
                    window.location.reload();
                } else {
                    btnRename.disabled = false;
                }
            });
        }

        colRight.appendChild(rename);
    }
}

/** Adds the redirect card to the right column. */
function renderRedirectCard(colRight, path, d) {
    const redirectTpl = document.getElementById('tpl-redirect-card');
    if (redirectTpl) {
        const redirect = redirectTpl.content.cloneNode(true);
        const redirectInput = redirect.querySelector('#pw-input-redirect-url');
        const btnToggleRedirect = redirect.querySelector('#pw-btn-toggle-redirect');
        const badgeRedirectActive = redirect.querySelector('#pw-badge-redirect-active');

        let savedUrl = (d.Settings?.redirect_url || '').trim();
        let isRedirectActive = !!(d.Settings?.enable_redirect && savedUrl !== '');

        const renderRedirectState = () => {
            if (isRedirectActive) {
                redirectInput.value = savedUrl;
                redirectInput.disabled = true;
                btnToggleRedirect.disabled = false;
                btnToggleRedirect.textContent = __('common.disable') || 'Disable';
                btnToggleRedirect.className = 'pw-btn pw-btn-danger';
                if (badgeRedirectActive) {
                    badgeRedirectActive.textContent = __('common.active') || 'Aktiv';
                    badgeRedirectActive.style.display = 'inline-block';
                }
            } else {
                redirectInput.value = savedUrl;
                redirectInput.disabled = false;
                btnToggleRedirect.disabled = (redirectInput.value.trim() === '');
                btnToggleRedirect.textContent = __('common.enable') || 'Enable';
                btnToggleRedirect.className = 'pw-btn pw-btn-primary';
                if (badgeRedirectActive) {
                    badgeRedirectActive.style.display = 'none';
                }
            }
        };

        const onInputChange = () => {
            if (isRedirectActive) return;
            btnToggleRedirect.disabled = (redirectInput.value.trim() === '');
        };

        redirectInput.addEventListener('input', onInputChange);
        redirectInput.addEventListener('change', onInputChange);

        redirectInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !btnToggleRedirect.disabled) {
                e.preventDefault();
                btnToggleRedirect.click();
            }
        });

        btnToggleRedirect.addEventListener('click', async () => {
            if (btnToggleRedirect.disabled) return;

            btnToggleRedirect.disabled = true;

            if (isRedirectActive) {
                // Disable redirect
                const result = await apiSafe('save_page_settings', {
                    path,
                    enable_redirect: false,
                    redirect_url: savedUrl
                });

                if (result && result.success !== false) {
                    notify(__('dashboard.redirect_saved'), 'success');
                    isRedirectActive = false;
                    renderRedirectState();
                    redirectInput.focus();
                } else {
                    btnToggleRedirect.disabled = false;
                }
            } else {
                // Enable redirect with the typed URL
                const url = redirectInput.value.trim();
                if (!url) {
                    btnToggleRedirect.disabled = true;
                    return;
                }

                const result = await apiSafe('save_page_settings', {
                    path,
                    enable_redirect: true,
                    redirect_url: url
                });

                if (result && result.success !== false) {
                    notify(__('dashboard.redirect_saved'), 'success');
                    savedUrl = url;
                    isRedirectActive = true;
                    renderRedirectState();
                } else {
                    btnToggleRedirect.disabled = false;
                }
            }
        });

        renderRedirectState();
        colRight.appendChild(redirect);

        // Attach PagePicker autocomplete to redirect URL input
        const mountedInput = colRight.querySelector('#pw-input-redirect-url');
        if (mountedInput && typeof PagePicker !== 'undefined') {
            new PagePicker(mountedInput);
        }
    }
}

/** Adds the move card to the right column. */
function renderMoveCard(colRight) {
    const moveTpl = document.getElementById('tpl-move-card');
    if (moveTpl) {
        colRight.appendChild(moveTpl.content.cloneNode(true));
    }
}

/** Adds the backlinks card to the left column. */
function renderBacklinksCard(colLeft, path) {
    const backlinksTpl = document.getElementById('tpl-backlinks-card');
    if (backlinksTpl) {
        const card = backlinksTpl.content.cloneNode(true);
        const cardContainer = card.firstElementChild;
        const listContainer = card.querySelector('[data-field="backlinks-list"]');
        const noBacklinksMsg = card.querySelector('[data-field="no-backlinks"]');
        const toggleWrapper = card.querySelector('[data-field="backlinks-toggle-wrapper"]');
        const toggleBtn = card.querySelector('[data-field="backlinks-toggle-btn"]');

        colLeft.appendChild(card);

        apiCall('get_backlinks', { path }).then(res => {
            if (res && res.enabled === false) {
                if (cardContainer && cardContainer.parentNode) {
                    cardContainer.remove();
                }
                return;
            }

            if (!res || !res.success || !Array.isArray(res.backlinks) || res.backlinks.length === 0) {
                noBacklinksMsg.style.display = 'block';
                return;
            }

            const backlinks = res.backlinks;
            listContainer.innerHTML = backlinks.map(b => {
                const subtitle = (b.title && b.title !== b.path) ? `<span style="color: var(--pw-text-muted); font-size: 0.8em; margin-left: 6px;">(${escapeHtml(b.path)})</span>` : '';
                return `<div class="pw-backlink-row" data-path="${escapeHtml(b.path)}">
                    <div style="display: flex; align-items: center; gap: 8px; min-width: 0; overflow: hidden;">
                        <iconify-icon icon="mdi:file-document-outline" style="color: var(--pw-text-muted); flex-shrink: 0;"></iconify-icon>
                        <span style="min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-size: 0.875rem;">
                            <strong style="font-weight: 500;">${escapeHtml(b.title || b.path)}</strong>${subtitle}
                        </span>
                    </div>
                    <iconify-icon icon="mdi:chevron-right" style="color: var(--pw-text-muted); flex-shrink: 0;"></iconify-icon>
                </div>`;
            }).join('');

            listContainer.onclick = (e) => {
                const row = e.target.closest('.pw-backlink-row');
                if (!row) return;
                const targetPath = row.getAttribute('data-path') || '';
                const cleanPath = (targetPath === '/' || targetPath.startsWith('/_')) ? targetPath : targetPath.replace(/^\//, '');
                const slashedPath = targetPath.startsWith('/') ? targetPath : '/' + targetPath;

                const treeItem = document.querySelector(`.pw-tree-item[data-path="${cleanPath}"], .pw-tree-item[data-path="${slashedPath}"]`);
                if (treeItem) {
                    let parentNode = treeItem.closest('.pw-tree-node');
                    while (parentNode) {
                        parentNode.classList.add('pw-expanded');
                        parentNode = parentNode.parentElement ? parentNode.parentElement.closest('.pw-tree-node') : null;
                    }
                    treeItem.click();
                    treeItem.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                } else {
                    sessionStorage.setItem('pw_active_page', cleanPath);
                    window.location.reload();
                }
            };

            if (backlinks.length > 5) {
                toggleWrapper.style.display = 'block';
                const chevron = toggleBtn.querySelector('iconify-icon');
                toggleBtn.onclick = () => {
                    const isExpanded = listContainer.classList.toggle('pw-expanded');
                    if (chevron) {
                        chevron.setAttribute('icon', isExpanded ? 'mdi:chevron-up' : 'mdi:chevron-down');
                    }
                    toggleBtn.setAttribute('title', isExpanded ? __('dashboard.show_less_backlinks') : __('dashboard.show_more_backlinks'));
                };
            }
        });
    }
}

/** Adds the comments card to the left column. */
function renderCommentsCard(colLeft, path) {
    const commentsTpl = document.getElementById('tpl-comments-card');
    if (commentsTpl) {
        const card = commentsTpl.content.cloneNode(true);
        const listContainer = card.querySelector('[data-field="comments-list"]');
        const noCommentsMsg = card.querySelector('[data-field="no-comments"]');
        const badge = card.querySelector('[data-field="pending-count"]');

        colLeft.appendChild(card);

        // Get comments from API
        apiCall('list_comments', { path })
        .then(res => {
            if (res && res.success && Array.isArray(res.data)) {
                const comments = res.data;
                if (comments.length === 0) {
                    noCommentsMsg.style.display = 'block';
                    return;
                }

                let pendingCount = 0;

                comments.forEach(c => {
                    if (c.status === 'pending') pendingCount++;

                    const item = document.createElement('div');
                    item.className = 'pw-comment-admin-item';
                    item.style.borderBottom = '1px solid var(--pw-border)';
                    item.style.padding = '10px 0';

                    const header = document.createElement('div');
                    header.style.display = 'flex';
                    header.style.justifyContent = 'space-between';
                    header.style.fontSize = '0.9rem';

                    const authorInfo = document.createElement('span');
                    authorInfo.innerHTML = `<strong>${escapeHtml(c.name)}</strong> <a href="mailto:${escapeHtml(c.email)}" class="pw-muted" style="margin-left:8px;font-size:0.8rem;">&lt;${escapeHtml(c.email)}&gt;</a>`;

                    const dateInfo = document.createElement('span');
                    dateInfo.className = 'pw-text-muted';
                    dateInfo.textContent = new Date(c.date).toLocaleString();

                    header.appendChild(authorInfo);
                    header.appendChild(dateInfo);
                    item.appendChild(header);

                    const body = document.createElement('div');
                    body.style.margin = '5px 0';
                    body.style.whiteSpace = 'pre-wrap';
                    body.textContent = c.text;
                    item.appendChild(body);

                    const footer = document.createElement('div');
                    footer.style.display = 'flex';
                    footer.style.gap = '10px';
                    footer.style.alignItems = 'center';
                    footer.style.marginTop = '8px';

                    // Status Badge
                    const statusBadge = document.createElement('span');
                    statusBadge.className = 'pw-badge';
                    if (c.status === 'approved') {
                        statusBadge.textContent = __('comments.status_approved') || 'Approved';
                        statusBadge.style.backgroundColor = 'rgba(40, 167, 69, 0.2)';
                        statusBadge.style.color = '#28a745';
                    } else if (c.status === 'hidden') {
                        statusBadge.textContent = __('comments.status_hidden') || 'Hidden';
                        statusBadge.style.backgroundColor = 'rgba(108, 117, 125, 0.2)';
                        statusBadge.style.color = '#6c757d';
                    } else {
                        statusBadge.textContent = __('comments.status_pending') || 'Pending';
                        statusBadge.style.backgroundColor = 'rgba(255, 193, 7, 0.2)';
                        statusBadge.style.color = '#ffc107';
                    }
                    footer.appendChild(statusBadge);

                    if (c.spam) {
                        const spamBadge = document.createElement('span');
                        spamBadge.className = 'pw-badge';
                        spamBadge.textContent = __('comments.spam') || 'Spam';
                        spamBadge.style.backgroundColor = 'rgba(220, 53, 69, 0.2)';
                        spamBadge.style.color = '#dc3545';
                        spamBadge.style.borderColor = 'rgba(220, 53, 69, 0.3)';
                        spamBadge.style.marginLeft = '5px';
                        footer.appendChild(spamBadge);
                    }

                    // Action Buttons
                    if (c.status === 'pending' || c.status === 'hidden') {
                        const btnApprove = document.createElement('button');
                        btnApprove.className = 'pw-btn pw-btn-primary';
                        btnApprove.style.padding = '2px 8px';
                        btnApprove.style.fontSize = '0.8rem';
                        btnApprove.textContent = __('comments.approve') || 'Approve';
                        btnApprove.onclick = async () => {
                            const ok = await apiSafe('moderate_comment', { path, comment_id: c.id, mod_action: 'approve' });
                            if (ok) {
                                const activeItem = document.querySelector('.pw-tree-item.pw-tree-active');
                                if (activeItem) activeItem.click();
                            }
                        };
                        footer.appendChild(btnApprove);
                    }

                    if (c.status === 'approved') {
                        const btnHide = document.createElement('button');
                        btnHide.className = 'pw-btn';
                        btnHide.style.padding = '2px 8px';
                        btnHide.style.fontSize = '0.8rem';
                        btnHide.textContent = __('comments.hide') || 'Hide';
                        btnHide.onclick = async () => {
                            const ok = await apiSafe('moderate_comment', { path, comment_id: c.id, mod_action: 'hide' });
                            if (ok) {
                                const activeItem = document.querySelector('.pw-tree-item.pw-tree-active');
                                if (activeItem) activeItem.click();
                            }
                        };
                        footer.appendChild(btnHide);
                    }

                    const btnDelete = document.createElement('button');
                    btnDelete.className = 'pw-btn pw-btn-danger';
                    btnDelete.style.padding = '2px 8px';
                    btnDelete.style.fontSize = '0.8rem';
                    btnDelete.textContent = __('common.delete') || 'Delete';
                    btnDelete.onclick = async () => {
                        const confirmed = await openDialog({
                            title: __('comments.delete') || 'Delete Comment',
                            text: __('comments.delete_confirm') || 'Are you sure you want to delete this comment?',
                            confirmText: __('common.delete') || 'Delete',
                            cancelText: __('common.cancel') || 'Cancel',
                            type: 'confirm'
                        });
                        if (confirmed) {
                            const ok = await apiSafe('moderate_comment', { path, comment_id: c.id, mod_action: 'delete' });
                            if (ok) {
                                const activeItem = document.querySelector('.pw-tree-item.pw-tree-active');
                                if (activeItem) activeItem.click();
                            }
                        }
                    };
                    footer.appendChild(btnDelete);

                    item.appendChild(footer);
                    listContainer.appendChild(item);
                });

                if (pendingCount > 0) {
                    badge.style.display = 'inline-block';
                    badge.textContent = pendingCount;
                }
            } else {
                noCommentsMsg.style.display = 'block';
            }
        })
        .catch(() => {
            noCommentsMsg.style.display = 'block';
        });
    }
}
