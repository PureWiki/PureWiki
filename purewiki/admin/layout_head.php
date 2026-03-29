<?php
/**
 * PureWiki - Admin Layout Head
 *
 * Reusable HTML <head> for admin pages
 *
 * @package   PureWiki
 * @author    Oliver Weinhold <oliverweinhold.de>
 * @copyright (c) 2026 by Oliver Weinhold
 * @license   GNU AGPLv3
 */

defined('PUREWIKI') || die('Direct access denied.');

if (!isset($lang)) {
    $lang = function_exists('getDashboardLanguage') ? getDashboardLanguage() : 'en';
}
if (!isset($theme)) {
    $theme = function_exists('getDashboardTheme') ? getDashboardTheme() : 'dark';
}
$themeAttr = $theme === 'light' ? ' data-theme="light"' : '';

if (!isset($pageTitle)) {
    $pageTitle = 'PureWiki';
}
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($lang); ?>"<?php echo $themeAttr; ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link rel="stylesheet" href="/purewiki/assets/css/core.css">
<?php
if (!isset($skipNotifyCss) || !$skipNotifyCss) {
    echo '    <link rel="stylesheet" href="/purewiki/assets/css/notify.css">' . "\n";
}
if (isset($extraCss)) {
    foreach ((array)$extraCss as $cssFile) {
        echo '    <link rel="stylesheet" href="' . htmlspecialchars($cssFile) . '">' . "\n";
    }
}
?>
    <?php
    if (class_exists('AssetManager')) {
        AssetManager::requireIconify();
        if (isset($requireCroppie) && $requireCroppie) {
            AssetManager::requireCroppie();
        }
        echo AssetManager::getStyles();
        echo AssetManager::getScripts('head');
    }
    if (isset($extraHead)) {
        echo $extraHead;
    }
    ?>
</head>
