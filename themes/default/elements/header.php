<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ title }}</title>
    <meta name="description" content="{{ description }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css">
    <link rel="stylesheet" href="{{ theme_url }}style.min.css">
    <script src="{{ theme_url }}script.min.js" defer></script>
    {{ assets_head }}

    <script>
        (function() {
            var t = localStorage.getItem('pw-theme');
            if (t === 'light' || t === 'dark') document.documentElement.setAttribute('data-theme', t);
        })();
    </script>
</head>
<body>
    <header class="container-fluid pw-header">
        <nav>
            <ul>
                <li>
                    {{ macro:sidebar_toggle }}
                </li>
                {{ if wiki_logo }}
                <li><a href="{{ base_url }}" class="logo"><img src="{{ wiki_logo }}" alt="{{ wiki_name }}"></a></li>
                {{ else }}
                <li><strong><a href="{{ base_url }}" class="contrast">{{ wiki_name }}</a></strong></li>
                {{ endif }}
            </ul>
            <ul class="pw-header-nav-right">
                {{ nav_links }}
                {{ macro:search }}
                <li class="pw-nav-keep">|</li>
                <li class="pw-nav-keep">
                    <button class="pw-theme-toggle" id="pw-theme-toggle" aria-label="Toggle theme">
                        <svg class="pw-theme-icon pw-theme-icon-light" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
                        <svg class="pw-theme-icon pw-theme-icon-dark" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                        <svg class="pw-theme-icon pw-theme-icon-auto" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 2a10 10 0 0 1 0 20z" fill="currentColor"/></svg>
                    </button>
                </li>
            </ul>
        </nav>
    </header>

