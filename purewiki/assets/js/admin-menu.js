/**
 * PureWiki - Admin Menu
 *
 * Handles the logic for the in-page administration menu
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

(function() {
    const menu = document.getElementById('pw-admin-menu');
    if (!menu) return;

    const dragHandle = menu.querySelector('.pw-admin-menu-drag-handle') || menu;
    let isDragging = false;
    let startY = 0;
    let startTop = 0;

    // Load saved position from localStorage
    const savedY = localStorage.getItem('pw-admin-menu-top');
    if (savedY !== null) {
        menu.style.top = savedY;
        menu.style.transform = 'translateY(0)';
    }

    dragHandle.addEventListener('mousedown', startDrag);
    dragHandle.addEventListener('touchstart', startDrag, { passive: false });

    function startDrag(e) {
        // Prevent default only if drag handle
        if (e.type === 'touchstart') {
            e.preventDefault();
            startY = e.touches[0].clientY;
        } else {
            startY = e.clientY;
        }

        isDragging = true;
        const rect = menu.getBoundingClientRect();
        startTop = rect.top;

        document.addEventListener('mousemove', onDrag);
        document.addEventListener('mouseup', stopDrag);
        document.addEventListener('touchmove', onDrag, { passive: false });
        document.addEventListener('touchend', stopDrag);

        menu.style.transition = 'none';
    }

    function onDrag(e) {
        if (!isDragging) return;

        let clientY;
        if (e.type === 'touchmove') {
            e.preventDefault();
            clientY = e.touches[0].clientY;
        } else {
            clientY = e.clientY;
        }

        const deltaY = clientY - startY;
        let newTop = startTop + deltaY;

        const menuHeight = menu.offsetHeight;
        const windowHeight = window.innerHeight;
        const margin = 20;

        if (newTop < margin) newTop = margin;
        if (newTop > windowHeight - menuHeight - margin) newTop = windowHeight - menuHeight - margin;

        menu.style.top = newTop + 'px';
        menu.style.transform = 'none';
    }

    function stopDrag() {
        if (!isDragging) return;
        isDragging = false;

        document.removeEventListener('mousemove', onDrag);
        document.removeEventListener('mouseup', stopDrag);
        document.removeEventListener('touchmove', onDrag);
        document.removeEventListener('touchend', stopDrag);

        menu.style.transition = '';
        localStorage.setItem('pw-admin-menu-top', menu.style.top);
    }
})();
