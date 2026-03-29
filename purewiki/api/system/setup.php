<?php
/**
 * PureWiki - Setup API
 *
 * Handles the initial system configuration and user creation.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

require_once __DIR__ . '/../../core/fs.php';

if ($action === 'setup_wiki') {
    // Security: Only allow if setup is NOT completed
    if (isSetupCompleted()) {
        $response['message'] = 'Setup has already been completed.';
        return;
    }

    $wikiName = trim($_POST['wiki_name'] ?? '');
    $wikiDesc = trim($_POST['wiki_description'] ?? '');
    $language = $_POST['language'] ?? 'en';
    $adminUser = trim($_POST['admin_username'] ?? '');
    $adminPass = $_POST['admin_password'] ?? '';

    if (empty($wikiName) || empty($adminUser) || empty($adminPass)) {
        $response['message'] = __('setup.error_fields');
        return;
    }

    // Saving config early so it is available for the rest of the setup
    $configData = [
        'wiki_name' => $wikiName,
        'wiki_description' => $wikiDesc, // Added to config (customizable later via settings)
        'dashboard_language' => $language,
        'setup_completed' => true,
        'setup_date' => date('c')
    ];

    try {
        if (!saveGlobalConfig($configData)) {
            $response['message'] = __('setup.error_save_config');
            return;
        }


        $userResult = createUser($adminUser, $adminPass, 'admin');
        if ($userResult !== true) {
        // Revert config flag on user creation error
        // Prevents lockout where setup is done but no admin exists
            saveGlobalConfig(['setup_completed' => false]);
            $response['message'] = is_string($userResult) ? $userResult : __('setup.error_create_user');
            return;
        }

        $response['success'] = true;
        $response['message'] = '';


        $pagesDir = getPageDir();
        if (!file_exists($pagesDir)) {
            createDirectory($pagesDir);
        }

        $startPageFile = $pagesDir . '/page.json';
        if (!file_exists($startPageFile)) {
            $initialContent = [
                'pagetitle' => 'Startpage',
                'blocks' => [
                    [
                        'id' => 'welcome-header',
                        'type' => 'header',
                        'data' => [
                            'text' => 'Welcome to your new PureWiki!',
                            'level' => 1
                        ]
                    ],
                    [
                        'id' => 'welcome-text',
                        'type' => 'paragraph',
                        'data' => [
                            'text' => 'This is your initial startpage. It has been automatically generated during setup. PureWiki is designed to be lightweight, fast, and easy to use without the need for complex databases.'
                        ]
                    ],
                    [
                        'id' => 'features-header',
                        'type' => 'header',
                        'data' => [
                            'text' => 'What\'s Next?',
                            'level' => 2
                        ]
                    ],
                    [
                        'id' => 'features-list',
                        'type' => 'list',
                        'data' => [
                            'style' => 'unordered',
                            'items' => [
                                'Head over to the <a href="/dashboard">Dashboard</a> to edit this page or create new ones.',
                                'Customize your wiki settings, navigation, and theme.',
                                'Have fun using PureWiki!'
                            ]
                        ]
                    ],
                    [
                        'id' => 'git-link-text',
                        'type' => 'paragraph',
                        'data' => [
                            'text' => 'For more information, documentation, or to report issues, visit the <a href="https://github.com/PureWiki/PureWiki" target="_blank">PureWiki GitHub Repository</a>.'
                        ]
                    ]
                ],
                'DateCreated' => date('c'),
                'Author' => $adminUser,
                'Settings' => [
                    'Layout' => 'page',
                    'hide_left_sidebar' => true,
                    'hide_right_sidebar' => true
                ]
            ];
            writeJsonFile($startPageFile, $initialContent);
        }
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }
}
