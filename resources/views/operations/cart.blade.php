<div class="hms-card space-y-4">
<h2>{{ $cartMode === 'sale' ? 'New paid sale' : 'New purchase order' }}</h2>
@if($cartMode === 'sale')<p class="hms-help">Internal sales record · tax calculation is not configured. Confirm payment before posting.</p>@endif
<label class="hms-field"><span>Find a product or scan a barcode</span><input type="search" x-model="query" @input.debounce.250ms="search()" @keydown.enter.prevent="results.length === 1 && add(results[0])" placeholder="Product name, SKU, or barcode" autocomplete="off"></label>
<p x-show="searching" x-cloak>Searching…</p><p x-show="searchError" x-text="searchError" class="hms-error" x-cloak></p>
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 max-h-64 overflow-auto">
<template x-for="product in results" :key="product.id"><button type="button" class="hms-secondary text-left justify-between" @click="add(product)"><span><span x-text="product.name"></span><small x-text="product.sku + ' · ' + Number(product.quantity_micro) / 1000000 + ' ' + product.base_unit + ' on hand'"></small></span><span aria-hidden="true">＋</span></button></template>
</div><p x-show="!searching && results.length === 0" x-cloak>No matching products. Add an active product in the Products tab.</p>
<div class="space-y-3">
<template x-for="(line, index) in lines" :key="line.product_id"><div class="hms-card space-y-3">
<div class="flex justify-between gap-3"><strong x-text="line.name"></strong><button type="button" class="underline text-sm" @click="lines.splice(index, 1)" :aria-label="'Remove ' + line.name">Remove</button></div>
<input type="hidden" :name="'lines['+index+'][product_id]'" :value="line.product_id">
<div class="hms-grid three"><label class="hms-field"><span>Unit</span><select :name="'lines['+index+'][unit_id]'" x-model="line.unit_id" @change="changeUnit(line)"><template x-for="unit in line.units" :key="unit.id"><option :value="unit.id" x-text="unit.label + ' (' + Number(unit.factor_micro) / 1000000 + ' ' + line.base_unit + ')'"></option></template></select></label>
<label class="hms-field"><span>Quantity</span><input type="number" step="0.000001" min="0.000001" required :name="'lines['+index+'][quantity]'" x-model="line.quantity"></label>
@if($cartMode === 'sale')<label class="hms-field"><span>Price per selected unit</span><input type="number" step="0.01" min="0" required :name="'lines['+index+'][price]'" x-model="line.price"></label><label class="hms-field"><span>Line discount</span><input type="number" step="0.01" min="0" required :name="'lines['+index+'][discount]'" x-model="line.discount"></label><label class="hms-field"><span>Discount type</span><select :name="'lines['+index+'][discount_type]'" x-model="line.discount_type"><option value="amount">Fixed amount (whole line)</option><option value="percent">Percent</option></select></label>
@else<label class="hms-field"><span>Acquisition cost per selected unit</span><input type="number" step="0.000001" min="0" required :name="'lines['+index+'][cost]'" x-model="line.cost"></label>@endif
<div><small>Line total</small><strong x-text="money(lineTotal(line))"></strong></div></div>
</div></template></div>
<p x-show="lines.length === 0">Your list is empty. Select a product above.</p>
<div class="flex justify-between border-t pt-4"><strong>Estimated total · {{ $store->currency }}</strong><strong class="text-2xl" x-text="money(total)"></strong></div><p class="hms-help">The server validates quantities, prices, payments, and final totals when you submit.</p>
</div>
