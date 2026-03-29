<?php
/**
 * PureWiki - Setup Wizard View
 *
 * Initial configuration interface for PureWiki.
 * Allows setting the wiki name, description, language, and creating the admin user.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

require_once __DIR__ . '/../core/i18n.php';
require_once __DIR__ . '/../core/asset_manager.php';

$defaultLang = 'en';
$GLOBALS['PW_DASHBOARD_LANG'] = $defaultLang;
$pageTitle = __('setup.page_title');
$lang = $defaultLang;
$translations = loadLanguage($lang);
$extraHead = '<style>
        .pw-setup-wrapper {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            gap: 24px;
            padding: 40px 24px;
        }
        .pw-setup-header {
            text-align: center;
            max-width: 500px;
        }
        .pw-setup-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--pw-text);
            margin: 0 0 8px 0;
            letter-spacing: -0.02em;
        }
        .pw-setup-intro {
            font-size: 0.95rem;
            color: var(--pw-text-muted);
            line-height: 1.5;
            margin: 0;
        }
        .pw-setup-card {
            width: 100%;
            max-width: 460px;
            background: var(--pw-bg-panel);
            border: 1px solid var(--pw-border);
            border-radius: 12px;
            padding: 32px;
            display: flex;
            flex-direction: column;
            gap: 24px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
        }
        .pw-setup-section {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        .pw-setup-section-title {
            font-size: 1rem;
            font-weight: 700;
            color: var(--pw-primary);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
            border-bottom: 1px solid var(--pw-border);
            padding-bottom: 8px;
        }
        .pw-setup-field {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .pw-setup-field label {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--pw-text-muted);
        }
        .pw-setup-field .pw-input {
            width: 100%;
        }
        .pw-setup-submit {
            width: 100%;
            justify-content: center;
            padding: 12px;
            font-size: 1rem;
            margin-top: 8px;
        }
        .pw-setup-error {
            background: rgba(220, 53, 69, 0.12);
            border: 1px solid var(--pw-danger);
            border-radius: 4px;
            color: var(--pw-danger);
            font-size: 0.87rem;
            padding: 10px 12px;
            display: none;
        }

        /* Welcome Animation Styles */
        .pw-welcome-screen {
            display: none;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            background: var(--pw-bg-panel);
            border: 1px solid var(--pw-border);
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 460px;
            animation: welcomeFadeIn 0.8s ease-out forwards;
        }

        .pw-welcome-icon {
            font-size: 4rem;
            color: var(--pw-success); /* Success green */
            margin-bottom: 20px;
            animation: bounceIn 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275) 0.3s forwards;
            opacity: 0;
            transform: scale(0.5);
        }

        .pw-welcome-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--pw-text);
            margin: 0 0 10px 0;
            opacity: 0;
            animation: slideUpFade 0.5s ease-out 0.6s forwards;
        }

        .pw-welcome-text {
            font-size: 1rem;
            color: var(--pw-text-muted);
            margin: 0;
            opacity: 0;
            animation: slideUpFade 0.5s ease-out 0.8s forwards;
        }

        .pw-welcome-redirect {
            font-size: 0.85rem;
            color: var(--pw-text-muted);
            margin-top: 24px;
            opacity: 0;
            animation: slideUpFade 0.5s ease-out 1.2s forwards;
        }

        @keyframes welcomeFadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes bounceIn {
            from { opacity: 0; transform: scale(0.5); }
            to { opacity: 1; transform: scale(1); }
        }

        @keyframes slideUpFade {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>';
require_once __DIR__ . '/layout_head.php';
?>
<body class="pw-dashboard-body">
    <div class="pw-setup-wrapper">
        <div class="pw-setup-header">
            <h1 class="pw-setup-title" data-i18n="setup.title"><?php echo __('setup.title'); ?></h1>
            <p class="pw-setup-intro" data-i18n="setup.intro"><?php echo __('setup.intro'); ?></p>
        </div>

        <form id="setup-form" class="pw-setup-card">
            <div id="setup-error" class="pw-setup-error"></div>

            <div class="pw-setup-section">
                <h2 class="pw-setup-section-title">
                    <iconify-icon icon="lucide:settings"></iconify-icon>
                    <span data-i18n="setup.wiki_settings"><?php echo __('setup.wiki_settings'); ?></span>
                </h2>
                <div class="pw-setup-field">
                    <label for="wiki_name" data-i18n="setup.wiki_name"><?php echo __('setup.wiki_name'); ?></label>
                    <input type="text" id="wiki_name" name="wiki_name" class="pw-input" 
                        placeholder="<?php echo htmlspecialchars(__('setup.wiki_name_placeholder')); ?>" 
                        data-i18n="setup.wiki_name_placeholder" required autofocus>
                </div>
                <div class="pw-setup-field">
                    <label for="wiki_description" data-i18n="setup.wiki_description"><?php echo __('setup.wiki_description'); ?></label>
                    <input type="text" id="wiki_description" name="wiki_description" class="pw-input" 
                        placeholder="<?php echo htmlspecialchars(__('setup.wiki_description_placeholder')); ?>"
                        data-i18n="setup.wiki_description_placeholder">
                </div>
                <div class="pw-setup-field">
                    <label for="language" data-i18n="setup.language"><?php echo __('setup.language'); ?></label>
                    <select id="language" name="language" class="pw-input">
                        <option value="en" <?php echo $lang === 'en' ? 'selected' : ''; ?>>English</option>
                        <option value="de" <?php echo $lang === 'de' ? 'selected' : ''; ?>>Deutsch</option>
                    </select>
                </div>
            </div>

            <div class="pw-setup-section">
                <h2 class="pw-setup-section-title">
                    <iconify-icon icon="lucide:user-plus"></iconify-icon>
                    <span data-i18n="setup.admin_user"><?php echo __('setup.admin_user'); ?></span>
                </h2>
                <p style="font-size: 0.85rem; color: var(--pw-text-muted); margin: -8px 0 0 0;" data-i18n="setup.admin_user_desc">
                    <?php echo __('setup.admin_user_desc'); ?>
                </p>
                <div class="pw-setup-field">
                    <label for="admin_username" data-i18n="setup.username"><?php echo __('setup.username'); ?></label>
                    <input type="text" id="admin_username" name="admin_username" class="pw-input" 
                        placeholder="<?php echo htmlspecialchars(__('setup.username_placeholder')); ?>" 
                        data-i18n="setup.username_placeholder" required autocomplete="username">
                </div>
                <div class="pw-setup-field">
                    <label for="admin_password" data-i18n="setup.password"><?php echo __('setup.password'); ?></label>
                    <input type="password" id="admin_password" name="admin_password" class="pw-input" 
                        placeholder="<?php echo htmlspecialchars(__('setup.password_placeholder')); ?>" 
                        data-i18n="setup.password_placeholder" required autocomplete="new-password">
                </div>
            </div>

            <button type="submit" id="setup-submit" class="pw-btn pw-btn-primary pw-setup-submit" data-i18n="setup.finish">
                <?php echo __('setup.finish'); ?>
            </button>
        </form>

        <div id="welcome-screen" class="pw-welcome-screen">
            <iconify-icon icon="lucide:check-circle" class="pw-welcome-icon"></iconify-icon>
            <h2 class="pw-welcome-title" id="welcome-title" data-i18n="setup.welcome"><?php echo __('setup.welcome'); ?></h2>
            <p class="pw-welcome-text" id="welcome-text" data-i18n="setup.success_created"><?php echo __('setup.success_created'); ?></p>
            <p class="pw-welcome-redirect"><iconify-icon icon="lucide:loader-2" class="pw-spin"></iconify-icon> <span data-i18n="setup.redirecting"><?php echo __('setup.redirecting'); ?></span></p>
        </div>
    </div>

    <script src="/purewiki/assets/js/notify.js"></script>
    <?php
    require_once realpath(__DIR__ . '/../core/json.php');
    $enLang = readJsonFile(__DIR__ . '/../lang/en.json');
    $deLang = readJsonFile(__DIR__ . '/../lang/de.json');
    ?>
    <script>
        const catalogs = {
            'en': <?php echo json_encode($enLang, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
            'de': <?php echo json_encode($deLang, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>
        };

        /**
         * Updates all translatable elements on the page based on the selected language.
         * @param {string} lang - Language code ('en', 'de').
         */
        function updateTranslations(lang) {
            const catalog = catalogs[lang];
            if (!catalog) return;

            document.querySelectorAll('[data-i18n]').forEach(el => {
                const key = el.getAttribute('data-i18n');
                const parts = key.split('.');
                let value = catalog;

                for (const part of parts) {
                    if (value && value[part]) {
                        value = value[part];
                    } else {
                        value = key;
                        break;
                    }
                }

                if (typeof value === 'string') {
                    if (el.tagName === 'INPUT' && el.hasAttribute('placeholder')) {
                        el.placeholder = value;
                    } else {
                        el.innerHTML = value;
                    }
                }
            });

            // Update page title
            document.title = catalog.setup.page_title || 'PureWiki – Setup';
            document.documentElement.lang = lang;
        }

        // Language switch listener
        document.getElementById('language').addEventListener('change', function() {
            updateTranslations(this.value);
        });

        // Form submission
        document.getElementById('setup-form').addEventListener('submit', async function(e) {
            e.preventDefault();

            const submitBtn = document.getElementById('setup-submit');
            const errorDiv = document.getElementById('setup-error');
            const lang = document.getElementById('language').value;
            const catalog = catalogs[lang];

            submitBtn.disabled = true;
            submitBtn.innerHTML = `<iconify-icon icon="lucide:loader-2" class="pw-spin"></iconify-icon> ${catalog.common.loading}`;
            errorDiv.style.display = 'none';

            const formData = new FormData(this);
            formData.append('action', 'setup_wiki');

            try {
                const res = await fetch('/purewiki/api.php', { method: 'POST', body: formData });
                const result = await res.json();

                if (result.success) {
                    document.getElementById('setup-form').style.display = 'none';
                    document.querySelector('.pw-setup-header').style.display = 'none';

                    const welcomeScreen = document.getElementById('welcome-screen');
                    welcomeScreen.style.display = 'flex';

                    setTimeout(() => {
                        window.location.href = '/';
                    }, 4000);
                } else {
                    errorDiv.textContent = result.message || catalog.setup.error_occurred;
                    errorDiv.style.display = 'block';
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = catalog.setup.finish;
                }
            } catch (error) {
                console.error('Setup error:', error);
                errorDiv.textContent = catalog.common.network_error;
                errorDiv.style.display = 'block';
                submitBtn.disabled = false;
                submitBtn.innerHTML = catalog.setup.finish;
            }
        });
    </script>
</body>
</html>
