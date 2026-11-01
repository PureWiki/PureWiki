<?php
/**
 * PureWiki - Login View
 *
 * Minimal authentication interface for the dashboard. Handles user
 * login and redirection to the main administration area.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/i18n.php';
require_once __DIR__ . '/../core/asset_manager.php';

// If already logged in, redirect immediately
if (isLoggedIn()) {
    header('Location: ' . BASE_PATH . '/dashboard');
    exit;
}

$error = '';
$wikiName = (getGlobalConfig())['wiki_name'] ?? 'PureWiki';

if (isset($_GET['cancel'])) {
    startAuth();
    cancelTwoFactorLogin();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post = fn(string $key) => is_string($_POST[$key] ?? null) ? $_POST[$key] : '';

    $result = isset($_POST['totp_code'])
        ? verifyTwoFactorLogin($post('totp_code'))
        : loginUser(trim($post('username')), $post('password'));

    if ($result !== true) {
        $error = $result;
    } elseif (getPendingTwoFactorUser() === null) {
        header('Location: ' . BASE_PATH . '/dashboard');
        exit;
    }
}
$pendingUser = getPendingTwoFactorUser();
$pageTitle = $wikiName . ' – ' . __('login.submit');
$extraHead = '<style>
        .pw-login-wrapper {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            gap: 24px;
            padding: 24px;
        }
        .pw-login-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--pw-text);
            margin: 0;
            letter-spacing: -0.02em;
        }
        .pw-login-card {
            width: 100%;
            max-width: 360px;
            background: var(--pw-bg-panel);
            border: 1px solid var(--pw-border);
            border-radius: 8px;
            padding: 28px;
            display: flex;
            flex-direction: column;
            gap: 18px;
        }
        .pw-login-field {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .pw-login-field label {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--pw-text-muted);
        }
        .pw-login-field .pw-input {
            width: 100%;
        }
        .pw-login-error {
            background: rgba(220, 53, 69, 0.12);
            border: 1px solid var(--pw-danger);
            border-radius: 4px;
            color: var(--pw-danger);
            font-size: 0.87rem;
            padding: 10px 12px;
        }
        .pw-login-submit {
            width: 100%;
            justify-content: center;
            padding: 10px;
            font-size: 0.95rem;
        }
        .pw-login-hint {
            font-size: 0.8rem;
            color: var(--pw-text-muted);
        }
        .pw-login-cancel {
            align-self: center;
            font-size: 0.85rem;
            color: var(--pw-text-muted);
        }
        .pw-login-cancel:hover {
            color: var(--pw-text);
        }
    </style>';
require_once __DIR__ . '/layout_head.php';
?>
<body class="pw-dashboard-body">
    <div class="pw-login-wrapper">
        <h1 class="pw-login-title"><?php echo htmlspecialchars($wikiName); ?></h1>

        <form class="pw-login-card" method="POST" action="<?php echo htmlspecialchars(BASE_PATH); ?>/dashboard/login">
            <?php if ($error): ?>
                <div class="pw-login-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($pendingUser !== null): ?>
                <div class="pw-login-field">
                    <label for="login-totp"><?php echo __('login.2fa_code'); ?></label>
                    <input type="text" id="login-totp" name="totp_code" class="pw-input"
                        inputmode="numeric" pattern="[0-9 ]*" maxlength="7" placeholder="123456"
                        autofocus autocomplete="one-time-code" required>
                    <span class="pw-login-hint"><?php echo __('login.2fa_hint'); ?></span>
                </div>

                <button type="submit" class="pw-btn pw-btn-primary pw-login-submit"><?php echo __('login.2fa_submit'); ?></button>
                <a class="pw-login-cancel" href="<?php echo htmlspecialchars(BASE_PATH); ?>/dashboard/login?cancel=1"><?php echo __('common.cancel'); ?></a>
            <?php else: ?>
                <div class="pw-login-field">
                    <label for="login-username"><?php echo __('login.username'); ?></label>
                    <input type="text" id="login-username" name="username" class="pw-input"
                        placeholder="admin" autofocus autocomplete="username">
                </div>

                <div class="pw-login-field">
                    <label for="login-password"><?php echo __('login.password'); ?></label>
                    <input type="password" id="login-password" name="password" class="pw-input"
                        placeholder="••••••••" autocomplete="current-password">
                </div>


                <button type="submit" class="pw-btn pw-btn-primary pw-login-submit"><?php echo __('login.submit'); ?></button>
            <?php endif; ?>
        </form>
    </div>
</body>
</html>
