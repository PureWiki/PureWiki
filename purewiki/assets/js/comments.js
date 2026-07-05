/**
 * PureWiki - Comments JS
 *
 * Script for managing and moderating comments globally
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

(function() {
    let allComments = [];
    let currentFilter = 'all';

    document.addEventListener('DOMContentLoaded', () => {
        initCommentsManager();
    });

    function initCommentsManager() {
        loadAllComments();
        bindFilters();
    }

    async function loadAllComments() {
        const wrapper = document.getElementById('pw-comments-list-wrapper');
        if (wrapper) {
            wrapper.innerHTML = `<p class="pw-hint">${__('common.loading')}</p>`;
        }

        try {
            const result = await apiCall('list_all_comments', {});
            if (result && result.success && Array.isArray(result.data)) {
                allComments = result.data;
                updatePendingBadge();
                renderComments();
            } else {
                if (wrapper) wrapper.innerHTML = `<p class="pw-text-danger">${result.message || __('common.error')}</p>`;
            }
        } catch (e) {
            if (wrapper) wrapper.innerHTML = `<p class="pw-text-danger">${__('common.error')}</p>`;
        }
    }

    function updatePendingBadge() {
        const badge = document.getElementById('pw-pending-badge');
        if (!badge) return;

        const pendingCount = allComments.filter(c => c.status === 'pending').length;
        if (pendingCount > 0) {
            badge.textContent = pendingCount;
            badge.style.display = 'inline-block';
        } else {
            badge.style.display = 'none';
        }
    }

    function bindFilters() {
        const buttons = document.querySelectorAll('.pw-comment-tab-btn');
        buttons.forEach(btn => {
            btn.addEventListener('click', () => {
                buttons.forEach(b => b.classList.remove('pw-btn-primary'));
                btn.classList.add('pw-btn-primary');
                currentFilter = btn.getAttribute('data-filter');
                renderComments();
            });
        });
    }

    function renderComments() {
        const wrapper = document.getElementById('pw-comments-list-wrapper');
        if (!wrapper) return;

        wrapper.innerHTML = '';

        const filtered = allComments.filter(c => {
            if (currentFilter === 'all') return true;
            return c.status === currentFilter;
        });

        if (filtered.length === 0) {
            wrapper.innerHTML = `<p class="pw-text-muted">${__('comments.no_comments')}</p>`;
            return;
        }

        const tpl = document.getElementById('tpl-comment-row');
        if (!tpl) return;

        filtered.forEach(c => {
            const row = tpl.content.cloneNode(true);
            const card = row.querySelector('.pw-comment-row-item');

            // Border color based on status
            if (c.status === 'approved') {
                card.style.borderLeftColor = '#28a745';
            } else if (c.status === 'hidden') {
                card.style.borderLeftColor = '#6c757d';
            } else {
                card.style.borderLeftColor = '#ffc107';
            }

            row.querySelector('[data-field="author"]').textContent = c.name;
            row.querySelector('[data-field="email"]').textContent = c.email;
            
            // Link to page details in dashboard
            const pageLink = row.querySelector('[data-field="page-link"]');
            pageLink.textContent = c.page_path === '/' ? __('dashboard.startpage') : c.page_path;
            pageLink.href = '#';
            pageLink.onclick = (e) => {
                e.preventDefault();
                sessionStorage.setItem('pw-active-page-path', c.page_path);
                window.location.href = window.PW_BASE_PATH + '/dashboard';
            };

            row.querySelector('[data-field="date"]').textContent = new Date(c.date).toLocaleString();
            row.querySelector('[data-field="text"]').textContent = c.text;

            // Status badge
            const badge = row.querySelector('[data-field="status-badge"]');
            if (c.status === 'approved') {
                badge.textContent = __('comments.status_approved') || 'Approved';
                badge.style.backgroundColor = 'rgba(40, 167, 69, 0.2)';
                badge.style.color = '#28a745';
            } else if (c.status === 'hidden') {
                badge.textContent = __('comments.status_hidden') || 'Hidden';
                badge.style.backgroundColor = 'rgba(108, 117, 125, 0.2)';
                badge.style.color = '#6c757d';
            } else {
                badge.textContent = __('comments.status_pending') || 'Pending';
                badge.style.backgroundColor = 'rgba(255, 193, 7, 0.2)';
                badge.style.color = '#ffc107';
            }

            const btnApprove = row.querySelector('.pw-comments-approve-btn');
            const btnHide = row.querySelector('.pw-comments-hide-btn');
            const btnDelete = row.querySelector('.pw-comments-delete-btn');

            if (c.status === 'pending' || c.status === 'hidden') {
                btnApprove.style.display = 'inline-flex';
                btnApprove.onclick = async () => {
                    const ok = await apiSafe('moderate_comment', { path: c.page_path, comment_id: c.id, mod_action: 'approve' });
                    if (ok) loadAllComments();
                };
            }

            if (c.status === 'approved') {
                btnHide.style.display = 'inline-flex';
                btnHide.onclick = async () => {
                    const ok = await apiSafe('moderate_comment', { path: c.page_path, comment_id: c.id, mod_action: 'hide' });
                    if (ok) loadAllComments();
                };
            }

            btnDelete.onclick = async () => {
                const confirmed = await openDialog({
                    title: __('comments.delete') || 'Delete Comment',
                    text: __('comments.delete_confirm') || 'Are you sure you want to delete this comment?',
                    confirmText: __('common.delete') || 'Delete',
                    cancelText: __('common.cancel') || 'Cancel',
                    type: 'confirm'
                });
                if (confirmed) {
                    const ok = await apiSafe('moderate_comment', { path: c.page_path, comment_id: c.id, mod_action: 'delete' });
                    if (ok) loadAllComments();
                }
            };

            wrapper.appendChild(row);
        });
    }
})();
