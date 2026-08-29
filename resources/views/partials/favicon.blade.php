@php
    $faviconPng = is_file(public_path('favicon-32x32.png'));
    $faviconIco = is_file(public_path('favicon.ico'));
    $appleIcon = is_file(public_path('apple-touch-icon.png'));
    $logoPng = is_file(public_path('images/cspc-logo.png'));
@endphp
@if ($faviconPng)
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
@elseif ($logoPng)
    <link rel="icon" type="image/png" href="{{ asset('images/cspc-logo.png') }}">
@endif
@if ($faviconIco)
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
@endif
@if ($appleIcon)
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
@endif
<meta name="theme-color" content="#1A365D">
