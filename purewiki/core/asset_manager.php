<?php
/**
 * PureWiki - Asset Manager
 *
 * Static manager for dynamically registering and fetching frontend assets (CSS/JS)
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

class AssetManager {
    private static $styles = [];
    private static $scripts = [
        'head' => [],
        'footer' => []
    ];

    /**
     * Registers a CSS stylesheet.
     * 
     * @param string $id Unique identifier for the stylesheet.
     * @param string $url URL to the CSS file.
     */
    public static function addStyle($id, $url) {
        if (!isset(self::$styles[$id])) {
            self::$styles[$id] = $url;
        }
    }

    /**
     * Registers a JavaScript file.
     * 
     * @param string $id Unique identifier for the script.
     * @param string $url URL to the JS file.
     * @param string $location Location for injection ('head' or 'footer').
     */
    public static function addScript($id, $url, $location = 'footer') {
        $loc = ($location === 'head') ? 'head' : 'footer';
        if (!isset(self::$scripts[$loc][$id])) {
            self::$scripts[$loc][$id] = $url;
        }
    }

    /** Returns all registered style tags as html */
    public static function getStyles() {
        $html = '';
        foreach (self::$styles as $url) {
            $html .= '<link rel="stylesheet" href="' . htmlspecialchars($url) . '">' . PHP_EOL;
        }
        return $html;
    }

    /** Returns all registered script tags for a specific location */
    public static function getScripts($location) {
        $loc = ($location === 'head') ? 'head' : 'footer';
        $attr = ($loc === 'footer') ? ' defer' : ' async';
        $html = '';
        foreach (self::$scripts[$loc] as $url) {
            $html .= '<script src="' . htmlspecialchars($url) . '"' . $attr . '></script>' . PHP_EOL;
        }
        return $html;
    }

    /** Helper to require Prism.js assets */
    public static function requirePrism() {
        self::addStyle('prism-okaidia', 'https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/themes/prism-okaidia.min.css');
        self::addScript('prism-core', 'https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/prism.min.js', 'footer');
        self::addScript('prism-autoloader', 'https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/plugins/autoloader/prism-autoloader.min.js', 'footer');
    }

    /** Helper to require Iconify */
    public static function requireIconify() {
        self::addScript('iconify', 'https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js', 'head');
    }

    /** Helper to require Croppie */
    public static function requireCroppie() {
        self::addStyle('croppie-css', 'https://cdnjs.cloudflare.com/ajax/libs/croppie/2.6.5/croppie.min.css');
        self::addScript('croppie-js', 'https://cdnjs.cloudflare.com/ajax/libs/croppie/2.6.5/croppie.min.js', 'footer');
    }
}
