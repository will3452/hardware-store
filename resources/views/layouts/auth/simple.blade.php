<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>@include('partials.head')</head>
    <body class="pyesio-auth antialiased">
        <div class="pyesio-auth-layout">
            <section class="pyesio-auth-story" aria-label="About Pyesio">
                <a href="{{ route('home') }}" class="pyesio-auth-logo" aria-label="Pyesio home" wire:navigate>
                    <img src="{{ Vite::asset('resources/images/brand/pyesio-logo-transparent.png') }}" alt="Pyesio. Hardware business, made simple." width="2172" height="724">
                </a>
                <div class="pyesio-auth-message">
                    <span class="pyesio-kicker">YOUR EVERYDAY STORE WORKSPACE</span>
                    <h1>A clearer view<br>of your <span>business.</span></h1>
                    <p>From the first delivery to the last sale, keep your products, stock, and daily numbers together.</p>
                    <div class="pyesio-auth-features" aria-label="Store workflows">
                        <span><flux:icon.cube variant="outline" class="size-5" /> Track your stock</span>
                        <span><flux:icon.shopping-cart variant="outline" class="size-5" /> Keep sales moving</span>
                        <span><flux:icon.chart-bar variant="outline" class="size-5" /> Know your numbers</span>
                    </div>
                </div>
                <p class="pyesio-auth-note">Built for the business you run every day.</p>
            </section>
            <main class="pyesio-auth-main">
                <a href="{{ route('home') }}" class="pyesio-auth-mobile-logo" aria-label="Pyesio home" wire:navigate>
                    <img src="{{ Vite::asset('resources/images/brand/pyesio-logo-transparent.png') }}" alt="Pyesio. Hardware business, made simple." width="2172" height="724">
                </a>
                <div class="pyesio-auth-card">{{ $slot }}</div>
                <p class="pyesio-auth-footer">{{ config('brand.tagline') }}</p>
            </main>
        </div>
        @persist('toast')<flux:toast.group><flux:toast /></flux:toast.group>@endpersist
        @fluxScripts
    </body>
</html>
