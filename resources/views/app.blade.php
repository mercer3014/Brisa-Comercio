<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title inertia>{{ config('app.name', 'Ovxel') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    {{-- Plus Jakarta Sans: familia unica del sistema (cuerpo + titulares en pesos altos). --}}
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800" rel="stylesheet" />

    @vite(['resources/js/app.js'])
    @inertiaHead
</head>
<body class="h-full font-sans antialiased bg-white text-institucional-900">
    @inertia
</body>
</html>
