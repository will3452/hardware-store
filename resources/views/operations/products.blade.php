<x-store-screen :store="$store" :page="$page" :modules="$modules">
@php
    $createOld = old('_form') === 'product-new';
    $next = session('product_next', []);
    $selectedUnit = $createOld ? old('base_unit', 'piece') : ($next['base_unit'] ?? 'piece');
@endphp
<div class="hms-top"><div><h2>Products</h2><p>Add a name, choose how you sell it, and set the price. We’ll generate the SKU.</p></div></div>
@if(($modules['catalog'] ?? null) === 'enabled')
<details class="hms-card" id="add-product" @if($products->isEmpty() || $createOld || $next) open @endif>
    <summary>Add a product</summary>
    <form method="POST" action="{{ route('store.products.save', $store->id) }}" class="space-y-5 mt-5"
          x-data="hmsProductForm(@js($sellingUnits), @js($selectedUnit))" @submit="submit($event)" @pageshow.window="busy = false"
          @if($next) x-init="$nextTick(() => $refs.productName.focus())" @endif>
        @csrf<input type="hidden" name="_form" value="product-new">
        <div class="hms-grid three">
            <div><x-field name="name" label="Product name & size" :use-old="$createOld" required maxlength="255" placeholder="e.g. Claw hammer, 16 oz" x-ref="productName"/><p class="hms-help mt-2">Include the size or variant to tell similar products apart.</p></div>
            <div><label class="hms-field"><span>Sell by</span><select name="base_unit" required x-model="unit">
                @foreach($sellingUnits as $key => $definition)<option value="{{ $key }}" @selected($selectedUnit === $key)>{{ $definition['label'] }}</option>@endforeach
            </select></label><p class="hms-help mt-2" x-text="fractional ? 'Allows decimals, such as 0.5 or 1.25.' : 'Sold in whole quantities: 1, 2, 3…'">{{ ($sellingUnits[$selectedUnit]['fractional'] ?? false) ? 'Allows decimals, such as 0.5 or 1.25.' : 'Sold in whole quantities: 1, 2, 3…' }}</p></div>
            <div><x-field name="price" :label="'Selling price ('.$store->currency.')'" :use-old="$createOld" type="number" step="0.01" min="0" max="999999999.99" inputmode="decimal" placeholder="0.00" required/><p class="hms-help mt-2">Price for 1 <span x-text="unit">{{ $selectedUnit }}</span>. Enter zero only for a free item.</p></div>
        </div>
        <details class="hms-product-options" @if($createOld || !empty($next['category']) || !empty($next['brand'])) open @endif>
            <summary>Optional details <span class="font-normal">· category, brand, barcode & stock alert</span></summary>
            <div class="hms-grid three mt-4">
                <x-product-choice name="category" label="Category" :choices="$categories" :value="$next['category'] ?? null" :use-old="$createOld"/>
                <x-product-choice name="brand" label="Brand" :choices="$brands" :value="$next['brand'] ?? null" :use-old="$createOld"/>
                <div><x-field name="barcode" label="Barcode" :use-old="$createOld" maxlength="100" placeholder="Scan or type the printed barcode" @keydown.enter.prevent="" autocomplete="off"/><p class="hms-help mt-2">Optional. A barcode can belong to only one product.</p></div>
                <div><x-field name="sku" label="Custom SKU" :use-old="$createOld" maxlength="80" placeholder="Generated automatically" autocomplete="off"/><p class="hms-help mt-2">Leave blank unless you already use your own product codes.</p></div>
                <div><x-field name="reorder" label="Low-stock alert at" :use-old="$createOld" type="number" step="1" x-bind:step="fractional ? '0.000001' : '1'" min="0" max="999999999" value="0"/><p class="hms-help mt-2">Alert when stock reaches this quantity or below. Zero means out of stock.</p></div>
            </div>
        </details>
        <p class="hms-help">Choose the smallest unit you sell. For example, sell nails by piece and add a box conversion later. This unit is fixed after saving.</p>
        <div class="hms-product-actions">
            <button class="hms-primary" name="save_action" value="save" x-bind:aria-disabled="busy">Save product</button>
            <button class="hms-secondary" name="save_action" value="another" x-bind:aria-disabled="busy">Save and add another</button>
            <span class="hms-help" x-show="busy" x-cloak role="status">Saving…</span>
        </div>
        <p class="hms-help">Adding another keeps your unit, category, and brand. Add opening stock in Inventory after saving.</p>
    </form>
</details>
@endif
<form class="hms-search" method="GET"><input name="q" value="{{ request('q') }}" placeholder="Search product, SKU, or barcode" aria-label="Search products"><button class="hms-secondary">Search</button></form>
<div class="hms-table-wrap"><table><thead><tr><th>Product</th><th>Price / unit</th><th>On hand</th><th>Status</th><th>Manage</th></tr></thead><tbody>
@forelse($products as $product)
<tr><td><strong>{{ $product->name }}</strong><small>{{ $product->sku }}{{ $product->brand ? ' · '.$product->brand : '' }}{{ $product->category ? ' · '.$product->category : '' }}</small></td><td>{{ number_format($product->price_minor / 100, 2) }} / {{ $product->base_unit }}</td><td>{{ App\Support\Amounts::display($product->quantity_micro, 6) }}</td><td>{{ $product->active ? 'Active' : 'Archived' }}</td><td><a href="#product-{{ $product->id }}" x-data @click="document.getElementById('product-{{ $product->id }}').open = true">Details ↓</a></td></tr>
@empty<tr><td colspan="5">No products found. Add a product to get started.</td></tr>@endforelse
</tbody></table></div>{{ $products->links() }}
@foreach($products as $product)
@php
    $editOld = old('_form') === 'product-'.$product->id;
    $unitOld = old('_form') === 'unit-'.$product->id;
@endphp
<details class="hms-card" id="product-{{ $product->id }}" @if($editOld || $unitOld) open @endif>
    <summary>{{ $product->name }} · {{ $product->sku }}</summary>
    <div class="space-y-5 mt-5">
        <p>Selling units: @foreach($units[$product->id] ?? [] as $unit)<span class="hms-badge">{{ $unit->label }} = {{ App\Support\Amounts::display($unit->factor_micro, 6) }} {{ $product->base_unit }}</span> @endforeach</p>
        @if(($modules['catalog'] ?? null) === 'enabled')
        <form method="POST" action="{{ route('store.products.save', $store->id) }}" class="space-y-4">
            @csrf<input type="hidden" name="_form" value="product-{{ $product->id }}"><input type="hidden" name="product_id" value="{{ $product->id }}"><input type="hidden" name="base_unit" value="{{ $product->base_unit }}"><input type="hidden" name="fractional" value="{{ (int) $product->fractional }}">
            <div class="hms-grid three">
                <x-field name="name" label="Product name & size" :value="$product->name" :use-old="$editOld" required maxlength="255"/>
                <x-field name="price" :label="'Price per '.$product->base_unit.' ('.$store->currency.')'" type="number" step="0.01" min="0" :value="App\Support\Amounts::display($product->price_minor)" :use-old="$editOld" required/>
                <x-field name="reorder" label="Low-stock alert at" type="number" :step="$product->fractional ? '0.000001' : '1'" min="0" :value="App\Support\Amounts::display($product->reorder_micro, 6)" :use-old="$editOld"/>
                <x-product-choice name="category" label="Category" :choices="$categories" :value="$product->category" :use-old="$editOld"/>
                <x-product-choice name="brand" label="Brand" :choices="$brands" :value="$product->brand" :use-old="$editOld"/>
                <x-field name="barcode" label="Barcode" :value="$product->barcode" :use-old="$editOld" maxlength="100" @keydown.enter.prevent=""/>
                <x-field name="sku" label="SKU" :value="$product->sku" :use-old="$editOld" required maxlength="80"/>
                <label class="hms-field"><span>Status</span><select name="active"><option value="1" @selected($editOld ? old('active') : $product->active)>Active</option><option value="0" @selected($editOld ? !old('active') : !$product->active)>Archived</option></select></label>
            </div>
            <button class="hms-secondary">Save changes</button>
        </form>
        <form method="POST" action="{{ route('store.units.save', $store->id) }}" class="hms-grid">
            @csrf<input type="hidden" name="_form" value="unit-{{ $product->id }}"><input type="hidden" name="product_id" value="{{ $product->id }}">
            <x-field name="label" label="Additional unit (e.g. box of 100)" :use-old="$unitOld" required maxlength="30"/>
            <x-field name="factor" :label="'1 additional unit contains how many '.$product->base_unit.'?'" :use-old="$unitOld" type="number" :step="$product->fractional ? '0.000001' : '1'" min="0.000001" required/>
            <p class="hms-help">Example: a box of 100 pieces contains 100. This conversion is fixed after saving.</p><button class="hms-secondary self-end">Add fixed conversion</button>
        </form>
        @endif
    </div>
</details>
@endforeach
</x-store-screen>
