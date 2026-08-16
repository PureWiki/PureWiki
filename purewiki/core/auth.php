<?php
/**
 * PureWiki - Authentication Helper
 *
 * Provides core functions for user management, session handling,
 * and secure password hashing using bcrypt.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

/** Returns the absolute path to users.json. */
function getUsersFilePath(): string {
    return __DIR__ . '/../../config/users.json';
}

/** Reads all users from users.json (array indexed by username). */
function readUsers(): array {
    try {
        $data = readJsonFile(getUsersFilePath());
        return is_array($data) ? $data : [];
    } catch (PureWikiException $e) {
        // Missing config is expected on first run; fallback to an empty state instead of hard crashing.
        return [];
    }
}

/** Writes the users array to users.json (creates directory if needed). */
function writeUsers(array $users): bool {
    $dir = dirname(getUsersFilePath());
    if (!file_exists($dir)) {
        require_once __DIR__ . '/fs.php';
        createDirectory($dir);
    }
    writeJsonFile(getUsersFilePath(), $users);
    return true;
}

/** Creates a new user with a hashed password. */
function createUser(string $username, string $password, string $role = 'admin'): bool|string {
    $username = trim($username);

    if (empty($username) || empty($password)) return 'Username and password are required.';
    if (!in_array($role, ['admin', 'editor', 'reader'])) return 'Invalid role specified.';
    if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $username)) return 'Username may only contain letters, numbers, underscores, hyphens, and dots.';

    $users = readUsers();
    if (isset($users[$username])) return 'A user with that username already exists.';

    $users[$username] = [
        'password_hash' => password_hash($password, PASSWORD_BCRYPT),
        'role'          => $role,
        'created_at'    => date('c')
    ];

    writeUsers($users);
    return true;
}

/** Deletes a user by username (prevents deleting last user). */
function deleteUser(string $username): bool|string {
    $users = readUsers();

    if (!isset($users[$username])) {
        return 'User not found.';
    }

    // Anti-lockout measure to prevent the system from becoming unmanageable.
    // If the last user is removed, nobody can log in anymore.
    if (count($users) <= 1) {
        return 'Cannot delete the only remaining user.';
    }

    unset($users[$username]);

    writeUsers($users);
    return true;
}

/** Returns a safe (no hashes) list of users for display. */
function listUsers(): array {
    $users = readUsers();
    $result = [];
    foreach ($users as $username => $data) {
        $result[] = [
            'username'      => $username,
            'role'          => $data['role'] ?? 'reader',
            'created_at'    => $data['created_at'] ?? '',
            'last_login_at' => $data['last_login_at'] ?? ''
        ];
    }
    return $result;
}

/** Starts the PHP session if not already active. */
function startAuth(): void {
    if (session_status() === PHP_SESSION_NONE) {
        // Support HTTPS detection even when deployed behind reverse proxies.
        // This ensures the `secure` cookie flag works correctly, mitigating session hijacking in proxy setups.
        $isSecure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ||
                    (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

        // Hardcoded 30-day lifetime
        session_set_cookie_params([
            'lifetime' => 60 * 60 * 24 * 30,
            'path'     => '/',
            'secure'   => $isSecure,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        session_start();
    }
}

/** Validates the session user against the users.json to check if user still exist and if role has changed. */
function validateSessionUser(): bool {
    $checkInterval = 60; // Interval in seconds before next check for changes in users.json file

    // Use cached result from $_SESSION if still within the interval
    if (isset($_SESSION['pw_last_user_check']) &&
        (time() - (int)$_SESSION['pw_last_user_check']) < $checkInterval) {
        return true;
    }

    $users    = readUsers();
    $username = $_SESSION['pw_user'];

    // Check if user was deleted from users.json
    if (!isset($users[$username])) {
        logoutUser();
        return false;
    }

    // Check if role changed since user logged in
    if (($users[$username]['role']) !== $_SESSION['pw_role']) {
        logoutUser();
        return false;
    }

    // Update cache timestamp
    $_SESSION['pw_last_user_check'] = time();
    return true;
}

/** Checks for an active, valid authenticated session. */
function isLoggedIn(): bool {
    startAuth();

    if (empty($_SESSION['pw_user']) || empty($_SESSION['pw_login_time'])) {
        return false;
    }

    // Enforce 30-day maximum session lifetime
    $maxAge = 60 * 60 * 24 * 30;
    if (time() - (int)$_SESSION['pw_login_time'] > $maxAge) {
        logoutUser();
        return false;
    }

    // If logged in, check if user still exists and role is the same
    return validateSessionUser();
}

function loginUser(string $username, string $password): bool|string {
    startAuth();

    $username = trim($username);
    if (empty($username) || empty($password)) {
        return 'Username and password are required.';
    }

    $users = readUsers();
    if (!isset($users[$username])) {
        return 'Invalid username or password.';
    }

    if (!password_verify($password, $users[$username]['password_hash'])) {
        return 'Invalid username or password.';
    }

    // Prevent session fixation by issuing a new ID after successful login
    session_regenerate_id(true);

    $_SESSION['pw_user']       = $username;
    $_SESSION['pw_role']       = $users[$username]['role'] ?? 'reader';
    $_SESSION['pw_login_time'] = time();

    // Write last login timestamp to users.json
    $users[$username]['last_login_at'] = date('c');
    writeUsers($users);

    require_once __DIR__ . '/activity.php';
    logActivity('user_login', 'auth');

    return true;
}

/** Destroys the current session and clears the session cookie. */
function logoutUser(): void {
    startAuth();
    $_SESSION = [];
    session_destroy();

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
}

/** Checks if the current user has the required role (hierarchy: admin > editor > reader). */
function hasRole(string $requiredRole): bool {
    if (!isLoggedIn()) return false;

    $levels = ['admin' => 3, 'editor' => 2, 'reader' => 1];
    $currentRole = $_SESSION['pw_role'] ?? 'reader';

    return ($levels[$currentRole] ?? 0) >= ($levels[$requiredRole] ?? 3);
}

/** Generate a CSRF token and stores it in the session. */
function getCsrfToken(): string {
    startAuth();
    if (empty($_SESSION['pw_csrf_token'])) {
        $_SESSION['pw_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['pw_csrf_token'];
}

/** Validate the CSRF token against the one stored in the session. */
function validateCsrfToken(string $token): bool {
    startAuth();
    if (empty($_SESSION['pw_csrf_token'])) return false;
    return hash_equals($_SESSION['pw_csrf_token'], $token);
}


