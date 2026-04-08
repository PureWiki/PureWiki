<?php
/**
 * PureWiki - Admin Menu
 *
 * Renders the floating admin menu for logged-in users in the frontend.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

/**
 * Generates and injects the Admin Menu into the HTML output.
 * 
 * @param string $html The HTML content to inject into.
 * @param array $config The global system configuration.
 * @param string $contextPath The current request path.
 */
function injectAdminMenu(string &$html, array $config, string $contextPath) {
    if (!isLoggedIn() || !($config['enable_admin_menu'] ?? false)) {
        return;
    }

    $adminMenuHtml = '
    <link rel="stylesheet" href="' . BASE_PATH . '/purewiki/assets/css/admin-menu.css">
    <div class="pw-admin-menu" id="pw-admin-menu">
        <div class="pw-admin-menu-drag-handle"></div>';

    if (hasRole('editor')) {
        $adminMenuHtml .= '
        <a href="' . BASE_PATH . '/dashboard/edit?path=' . urlencode($contextPath) . '" class="pw-admin-menu-item" data-tooltip="Edit Page">
            <iconify-icon icon="mdi:pencil"></iconify-icon>
        </a>
        <a href="' . BASE_PATH . '/dashboard" class="pw-admin-menu-item" data-tooltip="Dashboard">
            <iconify-icon icon="mdi:view-dashboard"></iconify-icon>
        </a>';
    }

    $adminMenuHtml .= '
        <a href="#" class="pw-admin-menu-item" data-tooltip="Logout" onclick="handleAdminLogout(); return false;">
            <iconify-icon icon="mdi:logout"></iconify-icon>
        </a>
    </div>
    <script>
        async function handleAdminLogout() {
            if (typeof apiCall === "function") {
                await apiCall("logout");
            } else {
                const fd = new FormData();
                fd.append("action", "logout");
                await fetch("' . BASE_PATH . '/purewiki/api.php", { method: "POST", body: fd });
            }
            window.location.href = window.PW_BASE_PATH + "/";
        }
    </script>
    <script src="' . BASE_PATH . '/purewiki/assets/js/admin-menu.js"></script>';

    if (str_contains($html, '</body>')) {
        $html = str_replace('</body>', $adminMenuHtml . '</body>', $html);
    } else {
        $html .= $adminMenuHtml;
    }
}
