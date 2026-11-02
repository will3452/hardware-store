@props(['sidebar' => false])
<a {{ $attributes->class(['pyesio-brand']) }} aria-label="{{ config('brand.name') }} home">
    <span class="pyesio-brand-mark"><x-app-logo-icon /></span>
    <span><span class="pyesio-brand-name">{{ config('brand.name') }}</span><span class="pyesio-brand-caption">Hardware management</span></span>
</a>
