@props(['store', 'page', 'modules' => []])
<x-layouts::app :title="ucfirst($page).' · '.$store->display_name">
<div class="hms-workspace">
    <div class="hms-top hms-masthead no-print"><div><p class="hms-eyebrow">STORE PORTAL / {{ $store->code }}</p><h1>{{ $store->display_name }}</h1><p>{{ $store->city }} · {{ $store->currency }} · {{ $store->timezone }}</p></div><a class="hms-secondary" href="{{ route('store.index') }}">Switch store</a></div>
    @php($icons = ['overview' => 'squares-2x2', 'sales' => 'shopping-cart', 'products' => 'cube', 'inventory' => 'archive-box', 'purchasing' => 'truck', 'cash' => 'banknotes', 'expenses' => 'receipt-percent', 'reports' => 'chart-bar', 'audit' => 'clock', 'profile' => 'cog-6-tooth'])
    <nav aria-label="Store modules" class="hms-nav no-print">
        @foreach (['overview' => 'Overview', 'sales' => 'Point of sale', 'products' => 'Products', 'inventory' => 'Inventory', 'purchasing' => 'Purchasing', 'cash' => 'Cash drawer', 'expenses' => 'Expenses', 'reports' => 'Reports', 'audit' => 'Activity', 'profile' => 'Store profile'] as $key => $label)
        @if ($key !== 'reports' || ($modules['reports'] ?? null) === 'enabled')
        <a @class(['active' => $page === $key]) @if($page === $key) aria-current="page" @endif href="{{ $key === 'overview' ? route('store.dashboard', $store->id) : route('store.page', [$store->id, $key]) }}"><flux:icon :name="$icons[$key]" variant="outline" class="size-4" />{{ $label }}</a>
        @endif
        @endforeach
    </nav>
    <x-notices />
    @php($moduleKey = $page === 'products' ? 'catalog' : $page)
    @if (isset($modules[$moduleKey]) && $modules[$moduleKey] !== 'enabled')<div class="hms-notice no-print">{{ ucfirst($moduleKey) }} is {{ $modules[$moduleKey] }}. History is retained; new work is unavailable.</div>@endif
    {{ $slot }}
</div>
</x-layouts::app>
