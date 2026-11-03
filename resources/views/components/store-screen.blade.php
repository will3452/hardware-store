@props(['store', 'page', 'modules' => []])
<x-layouts::app :title="ucfirst($page).' · '.$store->display_name">
<div class="hms-workspace">
    @if($readOnly)<aside class="hms-notice space-y-2" aria-label="Administrator viewing mode"><strong>Viewing as administrator · Read-only</strong><p>You are signed in as {{ auth()->user()->name }}. Store records cannot be changed in this view. Access is recorded in the audit trail.</p><a class="underline no-print" href="{{ route('admin.dashboard') }}">Back to Admin Portal</a></aside>@endif
    <div class="hms-top hms-masthead no-print"><div><p class="hms-eyebrow">STORE PORTAL / {{ $store->code }}</p><h1>{{ $store->display_name }}</h1><p>{{ $store->city }} · {{ $store->currency }} · {{ $store->timezone }}@if($readOnly) · {{ ucfirst(str_replace('_', ' ', $store->status)) }}@endif</p></div><a class="hms-secondary" href="{{ $readOnly ? route('admin.dashboard') : route('store.index') }}">{{ $readOnly ? 'Choose another store' : 'Switch store' }}</a></div>
    @php($icons = ['overview' => 'squares-2x2', 'sales' => 'shopping-cart', 'products' => 'cube', 'inventory' => 'archive-box', 'purchasing' => 'truck', 'cash' => 'banknotes', 'expenses' => 'receipt-percent', 'reports' => 'chart-bar', 'audit' => 'clock', 'profile' => 'cog-6-tooth'])
    <nav aria-label="Store modules" class="hms-nav no-print">
        @foreach (['overview' => 'Overview', 'sales' => 'Point of sale', 'products' => 'Products', 'inventory' => 'Inventory', 'purchasing' => 'Purchasing', 'cash' => 'Cash drawer', 'expenses' => 'Expenses', 'reports' => 'Reports', 'audit' => 'Activity', 'profile' => 'Store profile'] as $key => $label)
        @if ($readOnly || $key !== 'reports' || ($modules['reports'] ?? null) === 'enabled')
        <a @class(['active' => $page === $key]) @if($page === $key) aria-current="page" @endif href="{{ $key === 'overview' ? \App\Support\StoreWorkspace::route('store.dashboard', $store->id) : \App\Support\StoreWorkspace::route('store.page', [$store->id, $key]) }}"><flux:icon :name="$icons[$key]" variant="outline" class="size-4" />{{ $readOnly && $key === 'sales' ? 'Sales history' : $label }}</a>
        @endif
        @endforeach
    </nav>
    <x-notices />
    @php($moduleKey = $page === 'products' ? 'catalog' : $page)
    @if (isset($modules[$moduleKey]) && $modules[$moduleKey] !== 'enabled')<div class="hms-notice no-print">{{ ucfirst($moduleKey) }} is {{ $modules[$moduleKey] }}. History is retained; new work is unavailable.</div>@endif
    {{ $slot }}
</div>
</x-layouts::app>
