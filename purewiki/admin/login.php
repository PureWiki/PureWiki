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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $result = loginUser($username, $password);
    if ($result === true) {
        header('Location: ' . BASE_PATH . '/dashboard');
        exit;
    } else {
        $error = $result;
    }
}
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
        </form>
    </div>
</body>
</html>
