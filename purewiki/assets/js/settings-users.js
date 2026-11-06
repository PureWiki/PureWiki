/**
 * PureWiki - Settings Users
 *
 * User management tab of the general settings
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

/** Fetches the user list and renders it in the table. */
async function loadUsers() {
    const tbody = document.getElementById('pw-user-list');
    if (!tbody) return;

    tbody.innerHTML = '<tr><td colspan="4" style="padding: 12px; color: var(--pw-text-muted);">' + __('common.loading') + '</td></tr>';

    const result = await apiSafe('list_users', {}, { silent: true });

    if (result && result.data) {
        renderUserList(result.data);
    } else {
        tbody.innerHTML = '<tr><td colspan="4" style="padding: 12px; color: var(--pw-danger);">' + __('settings.failed_load_users') + '</td></tr>';
    }
}

/**
 * Renders the user list into the table body.
 * @param {Array} users - Array of user objects with username, role, and last_login_at.
 */
function renderUserList(users) {
    const tbody = document.getElementById('pw-user-list');
    if (!tbody) return;

    if (users.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" style="padding: 12px; color: var(--pw-text-muted);">' + __('settings.no_users') + '</td></tr>';
        return;
    }

    const isSingleUser = users.length === 1;
    const rowTpl = document.getElementById('tpl-user-row');
    tbody.innerHTML = '';

    users.forEach(user => {
        const row = rowTpl.content.cloneNode(true);
        row.querySelector('[data-field="username"]').textContent = user.username;
        row.querySelector('[data-field="role"]').textContent = user.role || 'admin';

        const displayNameEl = row.querySelector('[data-field="display_name"]');
        if (user.display_name) {
            displayNameEl.textContent = user.display_name;
        } else {
            displayNameEl.remove();
        }
        row.querySelector('.pw-user-display-name-btn').addEventListener('click', () => editUserDisplayName(user.username, user.display_name || ''));

        // Format last login date
        const lastLoginEl = row.querySelector('[data-field="last_login"]');
        if (user.last_login_at) {
            const d = new Date(user.last_login_at);
            lastLoginEl.textContent = d.toLocaleDateString(undefined, { year: 'numeric', month: '2-digit', day: '2-digit' }) + ' ' + d.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });
            lastLoginEl.title = user.last_login_at;
        } else {
            lastLoginEl.textContent = '\u2013';
            lastLoginEl.title = __('settings.never_logged_in');
        }

        const resetBtn = row.querySelector('.pw-user-2fa-reset-btn');
        if (user.totp_enabled) {
            resetBtn.addEventListener('click', () => resetUserTwoFactor(user.username));
        } else {
            row.querySelector('.pw-user-2fa-badge').remove();
            resetBtn.remove();
        }

        const btn = row.querySelector('.pw-user-delete-btn');
        btn.setAttribute('data-username', user.username);
        if (isSingleUser) {
            btn.disabled = true;
            btn.title = __('settings.cannot_delete_only_user');
        } else {
            btn.addEventListener('click', () => deleteUserEntry(user.username));
        }
        tbody.appendChild(row);
    });
}

/** Opens dialog to change or clear the display name of a user and reloads the list. */
async function editUserDisplayName(username, current) {
    const html = `
        <div style="display: flex; flex-direction: column; gap: 8px; margin-top: 5px;">
            <label for="pw-admin-display-name" style="display: block; font-size: 13px; color: var(--pw-text-muted);">${escapeHtml(__('settings.display_name_hint', username))}</label>
            <input id="pw-admin-display-name" type="text" class="pw-input pw-w-full" maxlength="100" value="${escapeHtml(current)}" placeholder="${__('auth.display_name_placeholder')}">
        </div>
    `;

    const confirmed = await openDialog({
        title: __('auth.change_display_name'),
        html: html,
        type: 'confirm',
        confirmText: __('common.save'),
        cancelText: __('common.cancel')
    });
    if (!confirmed) return;

    const input = document.getElementById('pw-admin-display-name');
    const displayName = input ? input.value.trim() : '';
    if (displayName === current) return;

    const result = await apiSafe('set_user_display_name', { username, display_name: displayName });
    if (result) {
        notify(result.message, 'success');
        loadUsers();
    }
}

/** Resets 2FA of a user via the API and reloads the list. */
async function resetUserTwoFactor(username) {
    const confirmed = await openDialog({
        title: __('settings.reset_2fa'),
        text: __('settings.reset_2fa_confirm', username),
        type: 'confirm',
        confirmText: __('common.confirm'),
        cancelText: __('common.cancel')
    });
    if (!confirmed) return;

    const result = await apiSafe('reset_user_2fa', { username });
    if (result) {
        notify(result.message, 'success');
        loadUsers();
    }
}

/** Deletes a user via the API and reloads the list. */
async function deleteUserEntry(username) {
    const result = await apiSafe('delete_user', { username });

    if (result) {
        notify(__('settings.user_deleted'), 'success');
        loadUsers();
    } else {
        notify(__('settings.error_delete_user'), 'error');
    }
}

/** Creates a new user via the API and reloads the list. */
async function addUser(e) {
    if (e) e.preventDefault();
    const usernameEl = document.getElementById('pw-new-username');
    const passwordEl = document.getElementById('pw-new-password');
    const roleEl = document.getElementById('pw-new-role');
    const displayNameEl = document.getElementById('pw-new-display-name');
    if (!usernameEl || !passwordEl) return;

    const username = usernameEl.value.trim();
    const password = passwordEl.value;
    const role = roleEl ? roleEl.value : 'admin';
    const displayName = displayNameEl ? displayNameEl.value.trim() : '';

    if (!username || !password) {
        notify(__('settings.username_password_required'), 'error');
        return;
    }

    const result = await apiSafe('create_user', { username, password, role, display_name: displayName });

    if (result) {
        notify(__('settings.user_created'), 'success');
        usernameEl.value = '';
        passwordEl.value = '';
        if (displayNameEl) displayNameEl.value = '';
        if (roleEl) roleEl.value = 'admin';
        loadUsers();
    }
}
