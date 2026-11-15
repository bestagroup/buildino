<!doctype html>
<html lang="fa" dir="rtl" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex,nofollow">
    <title>@yield('title', 'مدیریت واحد')</title>
    <link rel="stylesheet" href="{{ asset('css/buildino-fonts.css') }}">
    <link rel="stylesheet" href="{{ \App\Support\UiAssets::url('bootstrap.css') }}">
    <link rel="stylesheet" href="{{ \App\Support\UiAssets::url('sweetalert2.css') }}">
    @vite('resources/js/app.js')
    @stack('head')
    <link rel="stylesheet" href="{{ asset('css/buildino-foundation.css') }}">
    <link rel="stylesheet" href="{{ asset('css/buildino-management.css') }}">
    @stack('styles')
    <link rel="stylesheet" href="{{ asset('css/buildino-design-system.css') }}">
    <link rel="stylesheet" href="{{ asset('css/buildino-action-layout.css') }}?v={{ filemtime(public_path('css/buildino-action-layout.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/buildino-field-focus.css') }}?v={{ filemtime(public_path('css/buildino-field-focus.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/buildino-core-flows.css') }}">
    <link rel="stylesheet" href="{{ asset('css/buildino-unit-workspace.css') }}?v={{ filemtime(public_path('css/buildino-unit-workspace.css')) }}">
    <script src="{{ asset('js/buildino-unit-workspace.js') }}?v={{ filemtime(public_path('js/buildino-unit-workspace.js')) }}" defer></script>
</head>
<body data-glance-embedded>
<main id="mainContent">@yield('content')</main>
<script src="{{ \App\Support\UiAssets::url('bootstrap.js') }}" defer></script>
<script src="{{ \App\Support\UiAssets::url('sweetalert2.js') }}" defer></script>
<script src="{{ asset('js/buildino-numeric.js') }}?v={{ filemtime(public_path('js/buildino-numeric.js')) }}" defer></script>
<script src="{{ asset('js/buildino-foundation.js') }}" defer></script>
<script src="{{ \App\Support\UiAssets::url('jdate.js') }}" defer></script>
@include('shared.core-flow-config')
@include('shared.map-config')
@stack('scripts')
</body>
</html>
