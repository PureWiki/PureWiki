/**
 * PureWiki - Notification Helper
 *
 * Client-side notification system for displaying toast messages.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

document.addEventListener('DOMContentLoaded', () => {
    if (!document.getElementById('pw-notify-container')) {
        const container = document.createElement('div');
        container.id = 'pw-notify-container';
        document.body.appendChild(container);
    }

    // Check for pending notifications from before a reload
    const storedNotify = sessionStorage.getItem('pw-notify');
    if (storedNotify) {
        try {
            const parsed = JSON.parse(storedNotify);
            window.notify(parsed.text, parsed.type, parsed.duration);
        } catch (e) {
            console.error('Failed to parse pw-notify sessionStorage', e);
        }
        sessionStorage.removeItem('pw-notify');
    }
});

/**
 * Triggers an in-page notification toast
 * @param {string} text The message to display
 * @param {string} type 'success', 'error', 'info', 'warning'
 * @param {number} duration Time before auto-closing. Default: 5000
 */
window.notify = function(text, type = 'info', duration = 5000) {
    switch (type) {
        case 'error':
            console.error(text);
            break;
        case 'warning':
            console.warn(text);
            break;
        case 'success':
        case 'info':
        default:
            console.info(text);
            break;
    }

    const container = document.getElementById('pw-notify-container');
    if (!container) return;

    const toastHtml = `
        <span class="pw-notify-text"></span>
        <button class="pw-notify-close" type="button" aria-label="Close notification">&times;</button>
        <div class="pw-notify-progress"></div>
    `;

    const toast = document.createElement('div');
    toast.className = `pw-notify-toast pw-notify-toast-${type}`;
    toast.innerHTML = toastHtml;
    const textSpan = toast.querySelector('.pw-notify-text');
    textSpan.textContent = text;

    const closeBtn = toast.querySelector('.pw-notify-close');
    const progressBar = toast.querySelector('.pw-notify-progress');

    container.appendChild(toast);

    let remainingTime = duration;
    let startTime = Date.now();
    let timerId = null;
    let animationFrameId = null;

    const removeToast = () => {
        toast.classList.add('pw-closing');
        toast.addEventListener('animationend', () => toast.remove(), { once: true });
        setTimeout(() => toast.remove(), 350);
    };

    closeBtn.onclick = removeToast;

    const updateProgress = () => {
        if (!timerId) return;
        const elapsed = Date.now() - startTime;
        let percentage = 100 - ((elapsed / remainingTime) * 100);
        if (percentage < 0) percentage = 0;
        progressBar.style.transform = `scaleX(${percentage / 100})`;

        if (percentage > 0) {
            animationFrameId = requestAnimationFrame(updateProgress);
        }
    };

    const startTimer = () => {
        startTime = Date.now();

        progressBar.style.transition = 'none';

        animationFrameId = requestAnimationFrame(updateProgress);

        timerId = setTimeout(() => {
            removeToast();
        }, remainingTime);
    };

    const pauseTimer = () => {
        if (timerId) {
            clearTimeout(timerId);
            timerId = null;
        }
        if (animationFrameId) {
            cancelAnimationFrame(animationFrameId);
            animationFrameId = null;
        }
        remainingTime -= (Date.now() - startTime);
    };

    // Hover to pause
    toast.addEventListener('mouseenter', pauseTimer);
    toast.addEventListener('mouseleave', startTimer);

    startTimer();
};
