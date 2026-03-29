/**
 * PureWiki - Client-Side Internationalization (i18n)
 *
 * Provides a global __() function for retrieving translated strings
 * from the window.pwLang object injected by the PHP backend.
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

/**
 * Retrieves a translated string from the global language object.
 *
 * @param {string} key - Dot-notation key like 'settings.save_success'
 * @param {...string} args - Optional replacements for %s placeholders
 * @returns {string} Translated string or the key if not found
 */
function __(key, ...args) {
    const parts = key.split('.');
    let val = window.pwLang || {};
    for (const p of parts) {
        if (val && typeof val === 'object' && p in val) {
            val = val[p];
        } else {
            console.warn(`Missing translation string for key: "${key}"`);
            return key;
        }
    }
    if (typeof val !== 'string') {
        console.warn(`Translation for key "${key}" is not a string.`);
        return key;
    }
    if (args.length) {
        let i = 0;
        val = val.replace(/%s/g, () => args[i++] ?? '');
    }
    return val;
}