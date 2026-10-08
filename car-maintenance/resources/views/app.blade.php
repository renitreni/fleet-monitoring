<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    @inertiaHead
    @if ($page['props']['schema'] ?? null)
        <script type="application/ld+json" id="page-schema">{!! json_encode($page['props']['schema'], JSON_UNESCAPED_SLASHES) !!}</script>
    @endif
    @if (! $__inertiaSsrResponse)
        <title data-inertia>{{ config('app.name', 'Motologic') }} — Free Car Maintenance Tracker</title>
        <meta
            name="description"
            content="Motologic tracks mileage and oil changes, predicts your next service before the warning light, and recommends the right engine oil for your car — free."
        >
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="{{ config('app.name', 'Motologic') }}">
        <meta property="og:title" content="{{ config('app.name', 'Motologic') }} — Free Car Maintenance Tracker">
        <meta
            property="og:description"
            content="Motologic tracks mileage and oil changes, predicts your next service before the warning light, and recommends the right engine oil for your car — free."
        >
        <meta property="og:url" content="{{ url()->current() }}">
        <meta property="og:image" content="{{ asset('images/motologic-hero.png') }}">
        <meta name="twitter:card" content="summary_large_image">
    @endif
    <meta name="theme-color" content="#f3f1ec">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <script>
        (() => {
            const savedTheme = localStorage.getItem('motologic-theme');
            const theme = savedTheme || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            document.documentElement.classList.toggle('dark', theme === 'dark');
            document.documentElement.style.colorScheme = theme;
            document.querySelector('meta[name="theme-color"]').content = theme === 'dark' ? '#090b0d' : '#f3f1ec';
        })();
    </script>

    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.jsx'])
</head>
<body class="font-sans antialiased">
    @inertia
</body>
</html>
