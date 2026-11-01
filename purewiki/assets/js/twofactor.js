/**
 * PureWiki - Two-Factor Login
 *
 * Dialogs to set up and turn off the 2FA login of the current user.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

const QR_LIBRARY_URL = 'https://cdn.jsdelivr.net/npm/qrcode-generator@2.0.4/dist/qrcode.min.js';

document.addEventListener('DOMContentLoaded', () => {
    const button = document.getElementById('pw-btn-two-factor');
    if (!button) return;

    button.addEventListener('click', (e) => {
        e.stopPropagation();
        document.getElementById('pw-user-dropdown')?.classList.remove('pw-show');
        if (button.dataset.enabled === '1') {
            openDisableTwoFactorDialog();
        } else {
            openTwoFactorSetupDialog();
        }
    });
});

/** Loads the QR code library */
function loadQrCodeLibrary() {
    if (window.qrcode) return Promise.resolve(true);
    return new Promise((resolve) => {
        const script = document.createElement('script');
        script.src = QR_LIBRARY_URL;
        script.onload = () => resolve(!!window.qrcode);
        script.onerror = () => resolve(false);
        document.head.appendChild(script);
    });
}

/** Returns the QR code as SVG */
async function renderQrCode(text) {
    if (!await loadQrCodeLibrary()) return '';
    const qr = window.qrcode(0, 'M');
    qr.addData(text);
    qr.make();
    return qr.createSvgTag({ cellSize: 4, margin: 2, scalable: true });
}
function focusDialogInput(input) {
    setTimeout(() => input.focus(), 60);
}

/** Shows the API message and reloads */
function reloadWithMessage(res) {
    notify(res.message, 'success');
    setTimeout(() => window.location.reload(), 600);
}

/** Opens dialog to set up 2FA with an authenticator app */
async function openTwoFactorSetupDialog() {
    const setup = await apiSafe('start_2fa_setup');
    if (!setup) return;

    // QR code is drawn in the browser
    const qrSvg = await renderQrCode(setup.uri);
    const secretGroups = setup.secret.match(/.{1,4}/g).join(' ');

    const dialogPromise = openDialog({
        title: __('auth.2fa_enable'),
        html: `
            <div class="pw-2fa-dialog">
                <p>${__('auth.2fa_setup_text')}</p>
                ${qrSvg ? `<div class="pw-2fa-qr">${qrSvg}</div>` : ''}
                <a class="pw-btn pw-btn-primary pw-2fa-app-link" href="${escapeHtml(setup.uri)}"><iconify-icon icon="mdi:cellphone-key"></iconify-icon> ${__('auth.2fa_open_app')}</a>
                <div>
                    <label>${__('auth.2fa_secret')}</label>
                    <code class="pw-2fa-secret">${escapeHtml(secretGroups)}</code>
                </div>
                <div>
                    <label for="pw-2fa-code">${__('login.2fa_code')}</label>
                    <input id="pw-2fa-code" type="text" class="pw-input" inputmode="numeric" maxlength="7" placeholder="123456" autocomplete="one-time-code">
                </div>
            </div>
        `,
        type: 'confirm',
        confirmText: __('auth.2fa_activate'),
        cancelText: __('common.cancel')
    });

    const codeInput = document.getElementById('pw-2fa-code');
    const confirmBtn = document.getElementById('pw-dialog-btn-confirm');
    const isComplete = () => /^\d{6}$/.test(codeInput.value.replace(/\s/g, ''));

    confirmBtn.disabled = true;
    codeInput.addEventListener('input', () => confirmBtn.disabled = !isComplete());
    codeInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && isComplete()) confirmBtn.click();
    });
    focusDialogInput(codeInput);

    if (!await dialogPromise) return;

    const res = await apiSafe('enable_2fa', { code: codeInput.value });
    if (res) {
        reloadWithMessage(res);
    } else {
        // Same secret again, no new scan is needed
        openTwoFactorSetupDialog();
    }
}

/** Opens dialog to turn off 2FA */
async function openDisableTwoFactorDialog() {
    const dialogPromise = openDialog({
        title: __('auth.2fa_disable'),
        html: `
            <div class="pw-2fa-dialog">
                <p>${__('auth.2fa_disable_text')}</p>
                <div>
                    <label for="pw-2fa-password">${__('auth.current_password')}</label>
                    <input id="pw-2fa-password" type="password" class="pw-input" autocomplete="current-password">
                </div>
            </div>
        `,
        type: 'confirm',
        confirmText: __('auth.2fa_deactivate'),
        cancelText: __('common.cancel')
    });

    const passwordInput = document.getElementById('pw-2fa-password');
    focusDialogInput(passwordInput);

    if (!await dialogPromise) return;

    const res = await apiSafe('disable_2fa', { password: passwordInput.value });
    if (res) reloadWithMessage(res);
}
