<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">
        <meta name="theme-color" content="#176B52">

        <title>{{ config('app.name') }}</title>

        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="icon" href="/favicon.ico" sizes="any">

        @if (! app()->runningUnitTests())
            @fonts
            @viteReactRefresh
            @vite(['resources/css/app.css', 'resources/js/app.tsx'])
        @endif
    </head>
    <body class="font-sans antialiased">
        <div id="root"></div>
        <noscript>This application needs JavaScript to run.</noscript>
    </body>
</html>
