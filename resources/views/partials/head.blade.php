<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ filled($title ?? null) ? $title.' - '.config('brand.name') : config('brand.name') }}
</title>

<meta name="application-name" content="{{ config('brand.name') }}">
<meta name="theme-color" content="#0b2447">
<link rel="icon" href="{{ Vite::asset('resources/images/brand/pyesio-icon-transparent.png') }}" type="image/png">
<link rel="apple-touch-icon" href="{{ Vite::asset('resources/images/brand/pyesio-icon-transparent.png') }}">

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
