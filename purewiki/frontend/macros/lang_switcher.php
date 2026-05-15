<?php
/**
 * PureWiki - Frontend Macro: Language Switcher
 *
 * Renders a language switcher for the current page.
 * 
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

if (!function_exists('pw_render_lang_switcher')) {
    function pw_render_lang_switcher($contextPath, $style = 'list') {
        $config = getGlobalConfig();
        if (empty($config['i18n_enabled'])) return '';

        require_once __DIR__ . '/../../core/i18n_pages.php';
        
        $lang    = defined('CURRENT_LANG') ? CURRENT_LANG : '';
        $langs   = getSupportedPageLangs();
        $default = getDefaultPageLang();
        
        $pagePath = $contextPath ?? '';
        $fullPath = getPageDir() . '/' . ltrim($pagePath, '/');
        
        $available = [];
        if (file_exists($fullPath . '/page.json')) $available[] = '';
        foreach ($langs as $l) {
            if (file_exists($fullPath . '/page.' . $l . '.json')) {
                $available[] = $l;
            }
        }
        
        // Show current language and all available translations
        $toShow = array_unique(array_merge([$lang], $available));
        
        // Default first, then supported langs in order
        $finalLangs = [];
        if (in_array('', $toShow)) $finalLangs[] = '';
        foreach ($langs as $l) {
            if (in_array($l, $toShow)) $finalLangs[] = $l;
        }

        // If only one language available hide the switcher
        if (count($finalLangs) <= 1) return '';

        $baseUrl = rtrim(BASE_PATH, '/');
        
        if ($style === 'dropdown') {
            $html = '<nav class="pw-lang-switcher dropdown">';
            $html .= '<select id="pw-lang-select" name="pw-lang-select" onchange="window.location.href=this.value" class="pw-lang-select" aria-label="Select Language">';
            foreach ($finalLangs as $l) {
                $isActive = ($lang === $l);
                $label = $l === '' ? strtoupper($default) : strtoupper($l);
                
                $href = $baseUrl;
                if ($l !== '') $href .= '/' . $l;
                if ($pagePath !== '' && $pagePath !== '/') {
                    $href .= '/' . ltrim($pagePath, '/');
                }
                if ($href === '') $href = '/';
                
                $html .= '<option value="' . htmlspecialchars($href) . '"' . ($isActive ? ' selected' : '') . '>' . htmlspecialchars($label) . '</option>';
            }
            $html .= '</select>';
            $html .= '</nav>';
        } else {
            $html = '<nav class="pw-lang-switcher">';
            foreach ($finalLangs as $l) {
                $isActive = ($lang === $l);
                $label = $l === '' ? strtoupper($default) : strtoupper($l);
                
                $href = $baseUrl;
                if ($l !== '') $href .= '/' . $l;
                if ($pagePath !== '' && $pagePath !== '/') {
                    $href .= '/' . ltrim($pagePath, '/');
                }
                if ($href === '') $href = '/';
                
                $html .= '<a href="' . htmlspecialchars($href) . '" class="' . ($isActive ? 'active' : '') . '">' . htmlspecialchars($label) . '</a>';
            }
            $html .= '</nav>';
        }
        
        return $html;
    }
}

echo pw_render_lang_switcher($contextPath ?? '', $macroParam ?? 'list');