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

require_once __DIR__ . '/json.php';
require_once __DIR__ . '/fs.php';
require_once __DIR__ . '/config.php';

/** Returns the absolute path to users.json. */
function getUsersFilePath(): string {
    return getConfigDir() . '/users.json';
}

/** Returns the absolute path to login_attempts.json. */
function getLoginAttemptsFilePath(): string {
    return getConfigDir() . '/login_attempts.json';
}

/** Get client IP address */
function getAuthClientIp(): string {
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $ip = trim($_SERVER['HTTP_CF_CONNECTING_IP']);
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($parts[0]);
    } else {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '127.0.0.1';
}

/** Read login attempts and clean expired records. */
function readLoginAttempts(): array {
    $file = getLoginAttemptsFilePath();
    if (!file_exists($file)) {
        return [];
    }

    $attempts = readJson($file, []);
    if (!is_array($attempts)) {
        return [];
    }

    $now = time();
    $config = getGlobalConfig();
    $lockoutDuration = (int)($config['login_lockout_duration'] ?? 900);
    $cleanupThreshold = $now - max(3600, $lockoutDuration * 2);

    $cleaned = false;
    foreach ($attempts as $key => $entry) {
        $lastAttempt = (int)($entry['last_attempt'] ?? 0);
        $blockedUntil = (int)($entry['blocked_until'] ?? 0);
        if ($lastAttempt < $cleanupThreshold && $blockedUntil < $now) {
            unset($attempts[$key]);
            $cleaned = true;
        }
    }

    if ($cleaned) {
        writeLoginAttempts($attempts);
    }

    return $attempts;
}

/** Writes login attempts array to config/login_attempts.json. */
function writeLoginAttempts(array $attempts): bool {
    $dir = getConfigDir();
    if (!file_exists($dir)) {
        createDirectory($dir);
    }
    return writeJsonFile(getLoginAttemptsFilePath(), $attempts);
}

/** Checks if a login attempt is rate limited by IP or username. Returns remaining lockout seconds or 0. */
function isLoginRateLimited(string $username, ?string $ip = null): int {
    $ip = $ip ?? getAuthClientIp();
    $attempts = readLoginAttempts();
    $now = time();

    $keys = ['ip_' . md5($ip)];
    if (!empty($username)) {
        $keys[] = 'user_' . md5(strtolower($username));
    }

    $maxRemaining = 0;
    foreach ($keys as $key) {
        if (isset($attempts[$key]['blocked_until'])) {
            $remaining = (int)$attempts[$key]['blocked_until'] - $now;
            if ($remaining > $maxRemaining) {
                $maxRemaining = $remaining;
            }
        }
    }

    return $maxRemaining;
}

/** Record a failed login attempt for IP and username and activate lockout */
function recordFailedLoginAttempt(string $username, ?string $ip = null): void {
    $ip = $ip ?? getAuthClientIp();
    $attempts = readLoginAttempts();
    $config = getGlobalConfig();
    $maxAttempts = (int)($config['login_max_attempts'] ?? 5);
    $lockoutDuration = (int)($config['login_lockout_duration'] ?? 900);
    $now = time();

    $keys = ['ip_' . md5($ip)];
    if (!empty($username)) {
        $keys[] = 'user_' . md5(strtolower($username));
    }

    foreach ($keys as $key) {
        $entry = $attempts[$key] ?? ['count' => 0, 'last_attempt' => 0, 'blocked_until' => 0];

        if ($now - (int)$entry['last_attempt'] > $lockoutDuration) {
            $entry['count'] = 0;
        }

        $entry['count'] = ((int)$entry['count']) + 1;
        $entry['last_attempt'] = $now;

        if ($entry['count'] >= $maxAttempts) {
            $entry['blocked_until'] = $now + $lockoutDuration;
        }

        $attempts[$key] = $entry;
    }

    writeLoginAttempts($attempts);
}

/** Clears failed login attempts for IP and username on successful login. */
function clearLoginRateLimit(string $username, ?string $ip = null): void {
    $ip = $ip ?? getAuthClientIp();
    $attempts = readLoginAttempts();
    $keys = ['ip_' . md5($ip), 'user_' . md5(strtolower($username))];

    $changed = false;
    foreach ($keys as $key) {
        if (isset($attempts[$key])) {
            unset($attempts[$key]);
            $changed = true;
        }
    }

    if ($changed) {
        writeLoginAttempts($attempts);
    }
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
        createDirectory($dir);
    }
    writeJsonFile(getUsersFilePath(), $users);
    return true;
}

/** Returns error if password is shorter than 8 characters */
function checkPasswordLength(string $password): ?string {
    if (mb_strlen($password) >= 8) return null;
    return function_exists('__') ? __('auth.error_password_too_short') : 'Password must be at least 8 characters long.';
}

/** Creates a new user with a hashed password. */
function createUser(string $username, string $password, string $role = 'admin', string $displayName = ''): bool|string {
    $username = trim($username);
    $displayName = normalizeDisplayName($displayName);

    if (empty($username) || empty($password)) return 'Username and password are required.';
    if ($error = checkPasswordLength($password)) return $error;
    if (!in_array($role, ['admin', 'editor', 'reader'])) return 'Invalid role specified.';
    if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $username)) return 'Username may only contain letters, numbers, underscores, hyphens, and dots.';
    if (mb_strlen($displayName) > 100) {
        return function_exists('__') ? __('auth.error_display_name_too_long') : 'Display name may be at most 100 characters long.';
    }

    $users = readUsers();
    if (isset($users[$username])) return 'A user with that username already exists.';

    $users[$username] = [
        'password_hash' => password_hash($password, PASSWORD_BCRYPT),
        'role'          => $role,
        'created_at'    => date('c')
    ];
    if ($displayName !== '') {
        $users[$username]['display_name'] = $displayName;
    }

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
            'display_name'  => $data['display_name'] ?? '',
            'role'          => $data['role'] ?? 'reader',
            'created_at'    => $data['created_at'] ?? '',
            'last_login_at' => $data['last_login_at'] ?? '',
            'language'      => $data['language'] ?? '',
            'theme'         => $data['theme'] ?? '',
            'totp_enabled'  => !empty($data['totp_secret'])
        ];
    }
    return $result;
}

/** Changes the password for an existing user after verifying their current password. */
function changeUserPassword(string $username, string $currentPassword, string $newPassword): bool|string {
    $username = trim($username);
    if (empty($username) || empty($currentPassword) || empty($newPassword)) {
        return function_exists('__') ? __('auth.error_required') : 'All password fields are required.';
    }

    if ($error = checkPasswordLength($newPassword)) {
        return $error;
    }

    $users = readUsers();
    if (!isset($users[$username])) {
        return function_exists('__') ? __('auth.error_user_not_found') : 'User not found.';
    }

    if (!password_verify($currentPassword, $users[$username]['password_hash'])) {
        return function_exists('__') ? __('auth.error_current_password_invalid') : 'Current password is incorrect.';
    }

    $users[$username]['password_hash'] = password_hash($newPassword, PASSWORD_BCRYPT);
    writeUsers($users);

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }

    return true;
}

/** Sets the dashboard language for an existing user. */
function changeUserLanguage(string $username, string $language): bool|string {
    $username = trim($username);
    $language = trim($language);

    require_once __DIR__ . '/i18n.php';
    $available = getAvailableLanguages();
    if ($language !== '' && !isset($available[$language])) {
        return function_exists('__') ? __('auth.error_invalid_language') : 'Invalid language selected.';
    }

    $users = readUsers();
    if (!isset($users[$username])) {
        return function_exists('__') ? __('auth.error_user_not_found') : 'User not found.';
    }

    if ($language === '') {
        unset($users[$username]['language']);
    } else {
        $users[$username]['language'] = $language;
    }
    writeUsers($users);

    if (session_status() === PHP_SESSION_ACTIVE && ($_SESSION['pw_user'] ?? '') === $username) {
        if ($language === '') {
            unset($_SESSION['pw_lang']);
        } else {
            $_SESSION['pw_lang'] = $language;
        }
    }

    return true;
}

/** Sets the dashboard theme for an existing user. */
function changeUserTheme(string $username, string $theme): bool|string {
    $username = trim($username);
    $theme = trim($theme);

    if ($theme !== '' && !in_array($theme, ['dark', 'light'], true)) {
        return function_exists('__') ? __('auth.error_invalid_theme') : 'Invalid theme selected.';
    }

    $users = readUsers();
    if (!isset($users[$username])) {
        return function_exists('__') ? __('auth.error_user_not_found') : 'User not found.';
    }

    if ($theme === '') {
        unset($users[$username]['theme']);
    } else {
        $users[$username]['theme'] = $theme;
    }
    writeUsers($users);

    if (session_status() === PHP_SESSION_ACTIVE && ($_SESSION['pw_user'] ?? '') === $username) {
        if ($theme === '') {
            unset($_SESSION['pw_theme']);
        } else {
            $_SESSION['pw_theme'] = $theme;
        }
    }

    return true;
}

/** Trims a display name and collapses whitespace. */
function normalizeDisplayName(string $displayName): string {
    return trim(preg_replace('/\s+/u', ' ', $displayName) ?? '');
}

/** Sets public display name of a user. Empty name removes it. */
function changeUserDisplayName(string $username, string $displayName): bool|string {
    $username = trim($username);
    $displayName = normalizeDisplayName($displayName);

    if (mb_strlen($displayName) > 100) {
        return function_exists('__') ? __('auth.error_display_name_too_long') : 'Display name may be at most 100 characters long.';
    }

    $users = readUsers();
    if (!isset($users[$username])) {
        return function_exists('__') ? __('auth.error_user_not_found') : 'User not found.';
    }

    if ($displayName === '') {
        unset($users[$username]['display_name']);
    } else {
        $users[$username]['display_name'] = $displayName;
    }
    writeUsers($users);

    return true;
}

/** Returns public display name of user or empty string if none is set. */
function getUserDisplayName(string $username): string {
    if ($username === '') return '';
    return (string) (readUsers()[$username]['display_name'] ?? '');
}

/** Generates a 1-2 character uppercase initials badge string for a username. */
function getCurrentUserInitials(?string $username = null): string {
    $user = trim($username ?? ($_SESSION['pw_user'] ?? ''));
    if ($user === '') {
        return 'U';
    }

    $parts = preg_split('/[\s_\-\.]+/', $user, -1, PREG_SPLIT_NO_EMPTY);
    if (count($parts) >= 2) {
        return strtoupper(mb_substr($parts[0], 0, 1) . mb_substr($parts[1], 0, 1));
    }

    return strtoupper(mb_substr($user, 0, min(2, mb_strlen($user))));
}

/** Starts the PHP session if not already active. */
function startAuth(): void {
    if (session_status() === PHP_SESSION_NONE) {
        // Support HTTPS detection even when deployed behind reverse proxies.
        // This ensures the `secure` cookie flag works correctly, mitigating session hijacking in proxy setups.
        $isSecure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ||
                    (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

        if (!headers_sent()) {
            // Hardcoded 30-day lifetime
            session_set_cookie_params([
                'lifetime' => 60 * 60 * 24 * 30,
                'path'     => '/',
                'secure'   => $isSecure,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        }
        @session_start();
    }
}

/** Starts session if browser sent a session cookie */
function resumeAuth(): bool {
    if (session_status() === PHP_SESSION_NONE && empty($_COOKIE[session_name()])) {
        return false;
    }
    startAuth();
    return true;
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

    if (!empty($users[$username]['language'])) {
        $_SESSION['pw_lang'] = $users[$username]['language'];
    }

    if (!empty($users[$username]['theme'])) {
        $_SESSION['pw_theme'] = $users[$username]['theme'];
    }

    // Update cache timestamp
    $_SESSION['pw_last_user_check'] = time();
    return true;
}

/** Checks for an active, valid authenticated session. */
function isLoggedIn(): bool {
    if (!resumeAuth()) {
        return false;
    }

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
        return function_exists('__') ? __('login.error_required') : 'Username and password are required.';
    }

    $ip = getAuthClientIp();
    $remainingLockout = isLoginRateLimited($username, $ip);
    if ($remainingLockout > 0) {
        return getRateLimitMessage($remainingLockout);
    }

    $users = readUsers();
    if (!isset($users[$username]) || !password_verify($password, $users[$username]['password_hash'])) {
        recordFailedLoginAttempt($username, $ip);
        $remainingLockout = isLoginRateLimited($username, $ip);
        if ($remainingLockout > 0) {
            return getRateLimitMessage($remainingLockout);
        }
        return function_exists('__') ? __('login.error_invalid') : 'Invalid username or password.';
    }

    // Prevent session fixation by issuing a new ID after successful login
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }

    // Password is correct, 2FA code still missing
    if (!empty($users[$username]['totp_secret'])) {
        $_SESSION['pw_2fa_user'] = $username;
        $_SESSION['pw_2fa_time'] = time();
        return true;
    }

    finishLogin($username, $users, $ip);
    return true;
}

/** Returns user waiting for the 2FA code, or null. */
function getPendingTwoFactorUser(): ?string {
    startAuth();
    if (empty($_SESSION['pw_2fa_user'])) return null;

    // Code must follow within 5 minutes
    if (time() - (int)($_SESSION['pw_2fa_time'] ?? 0) > 300) {
        cancelTwoFactorLogin();
        return null;
    }
    return $_SESSION['pw_2fa_user'];
}

/** Cancels a login waiting for the 2FA code. */
function cancelTwoFactorLogin(): void {
    unset($_SESSION['pw_2fa_user'], $_SESSION['pw_2fa_time']);
}

/** Checks the 2FA code and logs in the user. */
function verifyTwoFactorLogin(string $code): bool|string {
    $username = getPendingTwoFactorUser();
    if ($username === null) {
        return function_exists('__') ? __('login.error_2fa_expired') : 'The login has expired. Please log in again.';
    }

    $ip = getAuthClientIp();
    if (isLoginRateLimited($username, $ip) === 0) {
        require_once __DIR__ . '/totp.php';
        $users = readUsers();
        $secret = $users[$username]['totp_secret'] ?? '';
        $step = $secret !== '' ? verifyTotpCode($secret, $code, (int)($users[$username]['totp_last_step'] ?? 0)) : null;

        if ($step !== null) {
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_regenerate_id(true);
            }
            $users[$username]['totp_last_step'] = $step;
            finishLogin($username, $users, $ip);
            return true;
        }
        recordFailedLoginAttempt($username, $ip);
    }

    $remainingLockout = isLoginRateLimited($username, $ip);
    if ($remainingLockout > 0) {
        cancelTwoFactorLogin();
        return getRateLimitMessage($remainingLockout);
    }
    return function_exists('__') ? __('login.error_2fa_invalid') : 'Invalid code.';
}

/** Returns the lockout message with the remaining minutes. */
function getRateLimitMessage(int $remainingSeconds): string {
    $minutes = max(1, (int)ceil($remainingSeconds / 60));
    return function_exists('__')
        ? __('login.error_rate_limited', $minutes)
        : "Too many failed login attempts. Please try again in {$minutes} minute(s).";
}

/** Stores the 2FA secret of a user, removes it if the secret is null. */
function setUserTotpSecret(string $username, ?string $secret, int $lastStep = 0): bool {
    $users = readUsers();
    if (!isset($users[$username])) return false;

    if ($secret === null) {
        unset($users[$username]['totp_secret'], $users[$username]['totp_last_step']);
    } else {
        $users[$username]['totp_secret'] = $secret;
        $users[$username]['totp_last_step'] = $lastStep;
    }
    return writeUsers($users);
}

/** Creates the session after successful login */
function finishLogin(string $username, array $users, string $ip): void {
    clearLoginRateLimit($username, $ip);
    cancelTwoFactorLogin();

    $_SESSION['pw_user']       = $username;
    $_SESSION['pw_role']       = $users[$username]['role'] ?? 'reader';
    $_SESSION['pw_login_time'] = time();
    if (!empty($users[$username]['language'])) {
        $_SESSION['pw_lang'] = $users[$username]['language'];
    }
    if (!empty($users[$username]['theme'])) {
        $_SESSION['pw_theme'] = $users[$username]['theme'];
    }

    // Write last login timestamp to users.json
    $users[$username]['last_login_at'] = date('c');
    writeUsers($users);

    require_once __DIR__ . '/activity.php';
    logActivity('user_login', 'auth');
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


