<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#121113">
    <title>{{ $meta['title'] ?? 'Owner Dashboard · Personal Portfolio' }}</title>
    @isset($meta)
        <meta name="description" content="{{ $meta['description'] }}">
        <meta property="og:type" content="website">
        <meta property="og:title" content="{{ $meta['title'] }}">
        <meta property="og:description" content="{{ $meta['description'] }}">
        <meta property="og:image" content="{{ $meta['image'] }}">
        <meta property="og:url" content="{{ $meta['url'] }}">
        <link rel="canonical" href="{{ $meta['url'] }}">
        <link rel="manifest" href="/manifest.webmanifest">
        <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
    @else
        <meta name="robots" content="noindex, nofollow">
    @endisset
    <link rel="icon" href="/icons/icon-192.png">
    @viteReactRefresh
    @vite(['resources/js/app.tsx'])
</head>
<body>
    @inertia
</body>
</html>
