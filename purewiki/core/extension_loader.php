<?php
/**
 * PureWiki - Extension Loader
 *
 * Discovers, validates, and boots all active extensions.
 * Provides the hook system (filters and actions) for the entire wiki.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

require_once __DIR__ . '/fs.php';
require_once __DIR__ . '/config.php';

class ExtensionLoader {

    /** Registered hooks keyed by name, then priority. */
    private static array $hooks = [];

    /** IDs of booted extensions. */
    private static array $loadedExtensions = [];

    /** Parsed meta.json data indexed by extension ID. */
    private static array $registry = [];

    /** Prevent double boot within a single request. */
    private static bool $booted = false;

    /**
     * Boot all active extensions.
     * Errors inside individual extension.php files are caught so one bad extension won't break entire wiki.
     */
    public static function boot(): void {
        if (self::$booted) return;
        self::$booted = true;

        $extensionsDir = getExtensionsDir();
        if (!is_dir($extensionsDir)) return;

        $config = getGlobalConfig();
        // The 'extensions' key holds enabled/disabled flags per extension ID.
        $extensionStates = $config['extensions'] ?? [];

        $entries = scandir($extensionsDir);
        if (!$entries) return;

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $extDir = $extensionsDir . DIRECTORY_SEPARATOR . $entry;
            if (!is_dir($extDir)) continue;

            $meta = self::loadMeta($extDir, $entry);
            if ($meta === null) continue;

            self::$registry[$entry] = $meta;

            // Skip if the extension is not enabled.
            $enabled = $extensionStates[$entry]['enabled'] ?? false;
            if (!$enabled) continue;

            self::bootExtension($entry, $extDir, $meta);
        }
    }

    /**
     * Registers a callback for a named hook at a given priority.
     * Lower priority numbers run first.
     *
     * @param string   $hook     Hook name such as 'renderer.html' or 'admin.head_css'.
     * @param callable $callback The function to invoke when the hook runs.
     * @param int      $priority Execution order, lower numbers run earlier (default 10).
     */
    public static function addHook(string $hook, callable $callback, int $priority = 10): void {
        self::$hooks[$hook][$priority][] = $callback;
    }

    /**
     * Applies a filter hook, passing $value through every registered callback in priority order.
     * Each callback receives ($value, $context) and must return the modified value.
     *
     * @param string $hook    Hook name.
     * @param mixed  $value   The value to filter.
     * @param array  $context Optional extra data passed to every callback.
     * @return mixed The filtered value after all callbacks have run.
     */
    public static function applyFilter(string $hook, mixed $value, array $context = []): mixed {
        if (empty(self::$hooks[$hook])) return $value;

        $priorityBuckets = self::$hooks[$hook];
        ksort($priorityBuckets);

        foreach ($priorityBuckets as $callbacks) {
            foreach ($callbacks as $cb) {
                $value = $cb($value, $context);
            }
        }

        return $value;
    }

    /**
     * Runs an action hook, invoking all registered callbacks in priority order.
     * No return value is expected or collected.
     *
     * @param string $hook    Hook name.
     * @param array  $context Extra data passed to every callback.
     */
    public static function doAction(string $hook, array $context = []): void {
        if (empty(self::$hooks[$hook])) return;

        $priorityBuckets = self::$hooks[$hook];
        ksort($priorityBuckets);

        foreach ($priorityBuckets as $callbacks) {
            foreach ($callbacks as $cb) {
                $cb($context);
            }
        }
    }

    /**
     * Returns the IDs of all booted extensions.
     * @return string[]
     */
    public static function getAll(): array {
        return self::$loadedExtensions;
    }

    /**
     * Returns the parsed meta.json for a specific extension, null if not found.
     * Works for active and inactive extensions as long as their meta.json is valid.
     *
     * @param string $id Extension ID (same as folder name)
     */
    public static function getMeta(string $id): ?array {
        return self::$registry[$id] ?? null;
    }

    /**
     * Returns the public URL to an extensions folder.
     * @param string $id Extension ID (same as folder name)
     */
    public static function getUrl(string $id): string {
        return (defined('BASE_PATH') ? BASE_PATH : '') . '/extensions/' . $id;
    }

    /**
     * Returns the full registry of all found extensions.
     */
    public static function getRegistry(): array {
        return self::$registry;
    }

    /**
     * Reads and validates meta.json for an extension directory.
     * Returns null (and logs a warning) if the file is missing, invalid, or the
     * extension ID in the file does not match the folder name.
     *
     * @param string $extDir  Absolute path to the extension folder.
     * @param string $dirName The folder name, which must match meta.json 'id'.
     */
    private static function loadMeta(string $extDir, string $dirName): ?array {
        $metaFile = $extDir . DIRECTORY_SEPARATOR . 'meta.json';
        if (!file_exists($metaFile)) return null;

        $raw = file_get_contents($metaFile);
        if ($raw === false) return null;

        $meta = json_decode($raw, true);
        if (!is_array($meta)) {
            error_log("ExtensionLoader: Invalid meta.json in '{$dirName}' (JSON parse error).");
            return null;
        }

        // ID in meta must match folder name and pass the regex.
        $id = $meta['id'] ?? '';
        if (!preg_match('/^[a-z0-9\-]+$/', $id) || $id !== $dirName) {
            error_log("ExtensionLoader: ID mismatch or invalid ID in '{$dirName}/meta.json' (got '{$id}').");
            return null;
        }

        // Required fields.
        foreach (['id', 'name', 'version', 'author'] as $field) {
            if (empty($meta[$field])) {
                error_log("ExtensionLoader: Missing required field '{$field}' in '{$dirName}/meta.json'.");
                return null;
            }
        }

        return $meta;
    }

    /**
     * Includes the extension's extension.php file inside a try/catch so one broken
     * extension cannot break the whole wiki.
     *
     * @param string $id     Extension ID.
     * @param string $extDir Absolute path to the extension folder.
     * @param array  $meta   Parsed meta data.
     */
    private static function bootExtension(string $id, string $extDir, array $meta): void {
        // Register extension translations if available
        $langDir = $extDir . DIRECTORY_SEPARATOR . 'lang';
        if (is_dir($langDir)) {
            require_once __DIR__ . '/i18n.php';
            loadExtensionTranslations($id, $langDir);
        }

        $entryFile = $extDir . DIRECTORY_SEPARATOR . 'extension.php';
        if (!file_exists($entryFile)) {
            error_log("ExtensionLoader: Missing extension.php for '{$id}'.");
            return;
        }

        try {
            require_once $entryFile;
            self::$loadedExtensions[] = $id;
        } catch (\Throwable $e) {
            error_log("ExtensionLoader: Failed to boot '{$id}': " . $e->getMessage());

            $config = getGlobalConfig();
            if (!empty($config['dev_debug_output'])) {
                trigger_error("ExtensionLoader [{$id}]: " . $e->getMessage(), E_USER_WARNING);
            }
        }
    }
}
