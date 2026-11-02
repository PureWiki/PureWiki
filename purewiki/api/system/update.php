<?php
/**
 * PureWiki - Update API
 *
 * Handles version checking and the update process via GitHub.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');


/** Replaces PureWiki block in the installed .htaccess. Dont update File without block. */
function updateHtaccessBlock(string $sourceFile, string $targetFile): void {
    $pattern = '/# BEGIN PureWiki\R.*?# END PureWiki/s';
    $source = is_file($sourceFile) ? file_get_contents($sourceFile) : false;
    $target = is_file($targetFile) ? file_get_contents($targetFile) : false;
    if ($source === false || $target === false) return;
    if (!preg_match($pattern, $source, $block) || !preg_match($pattern, $target)) return;

    $updated = preg_replace_callback($pattern, fn() => $block[0], $target, 1);
    if ($updated !== null && $updated !== $target) {
        file_put_contents($targetFile, $updated);
    }
}

/** Returns the URL of the release asset PureWiki-<tag>.zip or GitHub source archive if missing. */
function getReleaseZipUrl(array $release): string {
    $assetName = 'PureWiki-' . ($release['tag_name'] ?? '') . '.zip';
    foreach ($release['assets'] ?? [] as $asset) {
        if (($asset['name'] ?? '') === $assetName && !empty($asset['browser_download_url'])) {
            return $asset['browser_download_url'];
        }
    }
    return $release['zipball_url'] ?? '';
}

/** Downloads a file with cURL, returns error message or empty string on success. */
function downloadUpdateFile(string $url, string $targetFile, int $timeout): string {
    $fp = fopen($targetFile, 'w+');
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_USERAGENT, 'PureWiki-Updater/' . PUREWIKI_VERSION);
    applyCurlSslOptions($ch);

    $success = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    fclose($fp);

    if ($success && $httpCode === 200) return '';

    if (file_exists($targetFile)) unlink($targetFile);
    return 'HTTP: ' . $httpCode . ($curlError ? ' | cURL error: ' . $curlError : '');
}

/** Returns SHA256 hash from PureWiki-<tag>.zip.sha256 or null if missing or invalid. */
function getReleaseChecksum(string $zipUrl, string $tempFile): ?string {
    if (downloadUpdateFile($zipUrl . '.sha256', $tempFile, 30) !== '') return null;

    // Format of sha256sum: "<hash>  <file name>"
    $hash = strtolower((string) strtok(trim((string) file_get_contents($tempFile)), " \t"));
    unlink($tempFile);
    return preg_match('/^[a-f0-9]{64}$/', $hash) ? $hash : null;
}

/**
 * Compares two Version strings.
 * Uses SemVer 2.0.0 Version structure.
 * Returns 1 if $v1 > $v2, -1 if $v1 < $v2, 0 if equal.
 */
function compareSemVer(string $v1, string $v2): int {
    $parse = function($v) {
        $v = ltrim($v, 'vV');
        $parts = explode('-', $v, 2);
        $core = explode('.', $parts[0]);
        $pre = isset($parts[1]) ? explode('.', explode('+', $parts[1])[0]) : [];
        return ['core' => $core, 'pre' => $pre];
    };

    $p1 = $parse($v1);
    $p2 = $parse($v2);

    for ($i = 0; $i < 3; $i++) {
        $c1 = (int)($p1['core'][$i] ?? 0);
        $c2 = (int)($p2['core'][$i] ?? 0);
        if ($c1 > $c2) return 1;
        if ($c1 < $c2) return -1;
    }

    if (empty($p1['pre']) && !empty($p2['pre'])) return 1;
    if (!empty($p1['pre']) && empty($p2['pre'])) return -1;
    if (empty($p1['pre']) && empty($p2['pre'])) return 0;

    $len = max(count($p1['pre']), count($p2['pre']));
    for ($i = 0; $i < $len; $i++) {
        if (!isset($p1['pre'][$i])) return -1;
        if (!isset($p2['pre'][$i])) return 1;

        $pr1 = $p1['pre'][$i];
        $pr2 = $p2['pre'][$i];

        $isNum1 = ctype_digit($pr1);
        $isNum2 = ctype_digit($pr2);

        if ($isNum1 && !$isNum2) return -1;
        if (!$isNum1 && $isNum2) return 1;

        if ($isNum1 && $isNum2) {
            $pr1 = (int)$pr1;
            $pr2 = (int)$pr2;
        }

        if ($pr1 > $pr2) return 1;
        if ($pr1 < $pr2) return -1;
    }

    return 0;
}

if ($action === 'check_for_updates') {
    $currentVersion = PUREWIKI_VERSION;
    
    $config = getGlobalConfig();
    $allowPrerelease = !empty($config['allow_prerelease_updates']);
    
    $apiUrl = $allowPrerelease 
        ? "https://api.github.com/repos/purewiki/purewiki/releases"
        : "https://api.github.com/repos/purewiki/purewiki/releases/latest";

    if (!function_exists('curl_init')) {
        $response['message'] = 'PHP CURL extension is required for update checks.';
        return;
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'PureWiki-Updater/' . $currentVersion);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    applyCurlSslOptions($ch);

    $jsonResponse = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);

    if ($httpCode === 200 && $jsonResponse) {
        $releases = json_decode($jsonResponse, true);
        $targetRelease = null;

        if ($allowPrerelease && is_array($releases)) {
            $highestVer = '0.0.0';
            foreach ($releases as $rel) {
                if (!empty($rel['draft'])) continue;
                $ver = ltrim($rel['tag_name'], 'v');
                if (compareSemVer($ver, $highestVer) > 0) {
                    $highestVer = $ver;
                    $targetRelease = $rel;
                }
            }
        } else {
            $targetRelease = $releases;
        }

        if ($targetRelease && isset($targetRelease['tag_name'])) {
            $latestVersion = ltrim($targetRelease['tag_name'], 'v');
            $updateAvailable = compareSemVer($latestVersion, $currentVersion) > 0;

            require_once realpath(__DIR__ . '/../../extern/parsedown/Parsedown.php');
            require_once realpath(__DIR__ . '/../../extern/parsedownExtra/ParsedownExtra.php');
            $parsedown = new ParsedownExtra();
            $releaseNotes = $parsedown->text($targetRelease['body'] ?? '');

            $response['success'] = true;
            $response['data'] = [
                'current_version' => $currentVersion,
                'latest_version' => $latestVersion,
                'update_available' => $updateAvailable,
                'is_prerelease' => !empty($targetRelease['prerelease']),
                'release_name' => $targetRelease['name'] ?? $targetRelease['tag_name'],
                'release_notes' => $releaseNotes,
                'published_at' => $targetRelease['published_at'] ?? '',
                'zip_url' => getReleaseZipUrl($targetRelease),
                'html_url' => $targetRelease['html_url'] ?? ''
            ];
        } else {
            $response['message'] = 'Could not parse GitHub release information.';
        }
    } else {
        $response['message'] = 'Failed to fetch latest release from GitHub. HTTP: ' . $httpCode
            . ($curlError ? ' | cURL error: ' . $curlError : '');
    }
}

if ($action === 'get_update_requirements') {
    $rootDir = realpath(__DIR__ . '/../../../');
    $systemDir = realpath(__DIR__ . '/../../');
    $cacheDir = $rootDir . DIRECTORY_SEPARATOR . 'cache';

    if (!file_exists($cacheDir)) @createDirectory($cacheDir);

    $requirements = [
        'php_extensions' => [
            'zip' => ['name' => 'PHP Zip Extension', 'status' => extension_loaded('zip'), 'critical' => true, 'message' => 'Required for extracting.'],
            'curl' => ['name' => 'PHP CURL Extension', 'status' => function_exists('curl_init'), 'critical' => true, 'message' => 'Required for downloading.'],
            'openssl' => ['name' => 'OpenSSL Extension', 'status' => extension_loaded('openssl'), 'critical' => true, 'message' => 'Required for HTTPS.']
        ],
        'permissions' => [
            'root' => ['name' => 'Root Writable', 'status' => isTrulyWritable($rootDir), 'critical' => true, 'message' => 'Root dir must be writable.'],
            'system' => ['name' => 'System Writable', 'status' => isTrulyWritable($systemDir), 'critical' => true, 'message' => 'System dir must be writable.'],
            'cache' => ['name' => 'Cache Writable', 'status' => isTrulyWritable($cacheDir), 'critical' => true, 'message' => 'Cache dir must be writable.']
        ],
        'server' => [
            'max_execution_time' => ['name' => 'Max Execution Time', 'value' => ini_get('max_execution_time'), 'status' => (int)ini_get('max_execution_time') >= 30 || (int)ini_get('max_execution_time') === 0, 'critical' => false],
            'memory_limit' => ['name' => 'Memory Limit', 'value' => ini_get('memory_limit'), 'status' => true, 'critical' => false]
        ]
    ];

    $allCriticalMet = true;
    foreach ($requirements['php_extensions'] as $ext) if ($ext['critical'] && !$ext['status']) $allCriticalMet = false;
    foreach ($requirements['permissions'] as $perm) if ($perm['critical'] && !$perm['status']) $allCriticalMet = false;

    $response['success'] = true;
    $response['data'] = ['requirements' => $requirements, 'all_critical_met' => $allCriticalMet];
}

if ($action === 'download_update') {
    $zipUrl = $_POST['zip_url'] ?? '';

    $parsedUrl = parse_url($zipUrl);
    if ($parsedUrl === false) {
        $response['message'] = 'Invalid download source.';
        return;
    }

    $host = $parsedUrl['host'] ?? '';
    $scheme = $parsedUrl['scheme'] ?? '';
    $allowedHosts = ['github.com', 'api.github.com', 'codeload.github.com'];

    if ($scheme !== 'https' || !in_array($host, $allowedHosts)) {
        $response['message'] = 'Invalid download source.';
        return;
    }

    $updatesDir = __DIR__ . '/../../../cache/updates';
    if (!file_exists($updatesDir)) createDirectory($updatesDir);
    $targetFile = $updatesDir . '/update_temp.zip';

    $error = downloadUpdateFile($zipUrl, $targetFile, 120);
    if ($error !== '') {
        $response['message'] = 'Failed to download update package. ' . $error;
        return;
    }
    
    $isReleaseAsset = str_contains($parsedUrl['path'] ?? '', '/releases/download/');
    $expected = $isReleaseAsset ? getReleaseChecksum($zipUrl, $targetFile . '.sha256') : null;

    // A wrong checksum --> do not install
    if ($expected !== null && !hash_equals($expected, hash_file('sha256', $targetFile))) {
        unlink($targetFile);
        $response['message'] = 'Checksum of the update package does not match.';
        return;
    }

    // Without checksum --> dashboard asks before installing
    $response['success'] = true;
    $response['data'] = ['file_size' => filesize($targetFile), 'checksum_verified' => $expected !== null];
}

if ($action === 'start_pre_update_backup') {
    ensureBackupDirectoryExists();

    // createBackup() manages the lock file
    createBackup('pre_update');

    $response['success'] = true;
    $response['message'] = 'Pre-update backup completed.';
}

if ($action === 'get_update_backup_status') {
    $response['success'] = true;
    $response['running'] = isBackupRunning();
    $backups = getBackups();
    $latestUpdateBackup = null;
    foreach ($backups as $b) {
        if (str_contains($b['file'], 'pre_update') && (time() - $b['timestamp'] < 600)) {
            $latestUpdateBackup = $b; break;
        }
    }
    $response['backup'] = $latestUpdateBackup;
}

if ($action === 'install_update') {
    $updatesDir = __DIR__ . '/../../../cache/updates';
    $zipFile = $updatesDir . '/update_temp.zip';
    $extractDir = $updatesDir . '/extracted';

    // Clear tmp dir
    if (file_exists($extractDir)) {
        deleteDirectory($extractDir);
    }
    createDirectory($extractDir);

    $zip = new ZipArchive;
    if ($zip->open($zipFile) === TRUE) {
        $zip->extractTo($extractDir);
        $zip->close();
    } else {
        $response['message'] = 'Extraction of update package failed.'; return;
    }

    $items = array_diff(scandir($extractDir), ['.', '..']);
    $sourceRoot = $extractDir;
    if (count($items) === 1) {
        $first = reset($items);
        if (is_dir($extractDir . '/' . $first)) $sourceRoot = $extractDir . '/' . $first;
    }

    $targetRoot = realpath(__DIR__ . '/../../../');

    try {
        foreach (['index.php', 'router.php', 'nginx.conf.example'] as $file) {
            if (file_exists($sourceRoot . '/' . $file)) copy($sourceRoot . '/' . $file, $targetRoot . '/' . $file);
        }
        updateHtaccessBlock($sourceRoot . '/.htaccess', $targetRoot . '/.htaccess');
        if (is_dir($sourceRoot . '/purewiki')) copyDirectory($sourceRoot . '/purewiki', $targetRoot . '/purewiki', ['.git']);
        foreach (['default', 'default_picocss'] as $theme) {
            if (is_dir($sourceRoot . '/themes/' . $theme)) copyDirectory($sourceRoot . '/themes/' . $theme, $targetRoot . '/themes/' . $theme, ['.git']);
        }

        // Force cache rebuild
        if (function_exists('clearCache')) clearCache();

        $response['success'] = true;
    } catch (Exception $e) { $response['message'] = $e->getMessage(); }
}

if ($action === 'cleanup_update') {
    clearUpdateCache();
    $response['success'] = true;
}
