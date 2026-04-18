<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>GlobalAfrica+ — Connecter l'Afrique et sa Diaspora</title>
    <meta name="description" content="GlobalAfrica+ : la plateforme panafricaine qui transforme les remittances en investissements et crée des emplois durables.">

    {{-- Favicon --}}
    <link rel="icon" type="image/svg+xml" href="/brand/favicon.svg">
    <link rel="alternate icon" type="image/svg+xml" href="/brand/favicon.svg">
    <meta name="theme-color" content="#1A1A1A">

    {{-- Prevent dark mode FOUC (run before CSS paint) --}}
    <script>
        (function () {
            try {
                var t = localStorage.getItem('theme');
                var prefers = window.matchMedia('(prefers-color-scheme: dark)').matches;
                if (t === 'dark' || (!t || t === 'system') && prefers) {
                    document.documentElement.classList.add('dark');
                }
            } catch (e) {}
        })();
    </script>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-slate-100 antialiased">
    <div id="app"></div>
</body>
</html>
