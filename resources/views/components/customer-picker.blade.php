@props(['store', 'required' => false, 'selected' => null])
@php($chosen = $selected ?? (old('customer_id') ? App\Models\Customer::find(old('customer_id'))?->only(['id', 'name']) : null))
<div class="space-y-2" x-data="hmsCustomerPicker(@js(route('store.customers.search', $store->id)), @js($chosen))">
<label class="hms-field"><span>Find customer{{ $required ? '' : ' (optional for paid sales)' }}</span><input type="search" x-model="query" @input.debounce.250ms="search()" @keydown.enter.prevent="search()" placeholder="Type customer name" maxlength="100" autocomplete="off"></label>
<p class="hms-error" x-text="error" x-show="error" x-cloak></p>
<input type="hidden" name="customer_id" :value="selected?.id || ''">
<div class="flex flex-wrap gap-2 max-h-48 overflow-auto"><template x-for="item in results" :key="item.id"><button type="button" class="hms-secondary" @click="selected = item; results = []; query = ''; $dispatch('customer-selected', item)" x-text="item.name"></button></template></div>
<div x-show="selected" class="hms-notice" x-cloak><strong x-text="selected?.name"></strong><button type="button" class="underline ml-3" @click="selected = null">Clear selection</button><p x-show="selected?.balance !== undefined" x-text="selected ? 'Outstanding: ' + Number(selected.balance / 100).toFixed(2) + ' · Credit limit: ' + Number(selected.limit / 100).toFixed(2) + (selected.credit_enabled ? '' : ' · New credit is off') : ''"></p></div>
<p class="hms-error" x-show="selected?.overdue > 0" x-cloak>This customer has overdue debt. Review the account before granting additional credit.</p>
<p class="hms-help">Create a new account in Customers. Review the account for overdue amounts before granting more credit.</p>
</div>
