<x-store-screen :store="$store" :page="$page" :modules="$modules">
<x-onboarding-checklist :progress="$onboarding" :store="$store" :read-only="$readOnly"/>
<div class="hms-top"><div><h2>Today at a glance</h2><p>{{ now()->timezone($store->timezone)->format('l, F j, Y') }}</p></div>@if(!$readOnly && ($modules['sales'] ?? null) === 'enabled')<a class="hms-primary" href="{{ \App\Support\StoreWorkspace::route('store.page', [$store->id, 'sales']) }}">New sale →</a>@endif</div>
@if(isset($metrics))<x-metrics :metrics="$metrics" :currency="$store->currency" />@endif
<div class="hms-grid">
<section class="hms-card space-y-4"><h2>{{ $readOnly ? 'Store status' : 'Ready for the day' }}</h2><p>Cash drawer: <strong>{{ $session ? 'Open' : 'Closed' }}</strong></p><p>{{ $productCount }} products · Store {{ str_replace('_', ' ', $store->status) }}</p>
@if(!$readOnly && in_array($modules['cash'] ?? null, ['enabled', 'restricted']))<a class="hms-secondary" href="{{ \App\Support\StoreWorkspace::route('store.page', [$store->id, 'cash']) }}">{{ $session ? 'View cash drawer' : 'Open cash drawer' }} →</a>@endif
<p class="hms-help">Sales printouts are internal records. Continue using your registered invoicing process.</p></section>
<section class="hms-card space-y-4"><h2>Low stock</h2>@forelse($lowStock as $product)<div class="flex justify-between gap-3"><span>{{ $product->name }}<small class="block">{{ $product->sku }}</small></span><strong>{{ App\Support\Amounts::quantity($product->quantity_micro) }} {{ $product->base_unit }}</strong></div>@empty<p>No products are below their reorder level.</p>@endforelse</section>
</div>
<section class="space-y-4"><h2>Announcements</h2>@forelse($announcements as $announcement)<article class="hms-card"><h3>{{ $announcement->title }}</h3><p class="whitespace-pre-wrap mt-2">{{ $announcement->body }}</p></article>@empty<div class="hms-card"><p>No announcements right now.</p></div>@endforelse</section>
</x-store-screen>
