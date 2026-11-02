@props(['title' => 'Admin Portal'])
<x-layouts::app :title="$title">
<div class="hms-workspace"><div class="hms-top hms-masthead no-print"><div><p class="hms-eyebrow">PLATFORM WORKSPACE</p><h1>{{ $title }}</h1><p>Manage independent stores and see how each business is performing.</p></div><span class="hms-badge">Pyesio administration</span></div>
<nav class="hms-nav no-print" aria-label="Admin navigation">
    @foreach (['admin.dashboard' => ['Stores & reports', 'building-storefront', null], 'admin.announcements' => ['Announcements', 'megaphone', 'platform.announcements.manage'], 'admin.audit' => ['Audit trail', 'clock', 'platform.audit.read'], 'admin.health' => ['System status', 'signal', 'platform.stores.manage']] as $route => [$label, $icon, $permission])
        @if(!$permission || auth()->user()->hasPlatformPermission($permission))
            @php($current = request()->routeIs($route) || ($route === 'admin.dashboard' && request()->routeIs('admin.stores.*')))
            <a href="{{ route($route) }}" @class(['active' => $current]) @if($current) aria-current="page" @endif><flux:icon :name="$icon" variant="outline" class="size-4" />{{ $label }}</a>
        @endif
    @endforeach
</nav>
<x-notices />{{ $slot }}</div>
</x-layouts::app>
