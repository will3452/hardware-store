<x-store-screen :store="$store" :page="$page" :modules="$modules">
<nav class="hms-section-links no-print" aria-label="On this page"><span>Jump to</span><a href="#transaction-history">Sales history</a></nav>
@if(!$readOnly && ($modules['sales'] ?? null) === 'enabled')
@if(!$session)<div class="hms-notice">Open your cash drawer before recording sales. <a class="underline" href="{{ \App\Support\StoreWorkspace::route('store.page', [$store->id, 'cash']) }}">Open cash session →</a></div>@endif
<form method="POST" action="{{ route('store.post', [$store->id, 'sale']) }}" x-data="hmsCart(@js(route('store.catalog', $store->id)), 'sale', @js($oldCart), @js(old('payments', [])), @js($store->acceptedPayments()))" @submit="submit($event)" @pageshow.window="busy = false" class="space-y-5">
<x-operation-token/><input type="hidden" name="cash_session_id" value="{{ $session?->id }}">
@include('operations.cart', ['cartMode' => 'sale'])
<div class="hms-card space-y-4">
<h2>Payment</h2><p>Enter what you received. Split payments can use different methods; change comes from cash.</p>
<template x-for="(payment, index) in payments" :key="index"><div class="hms-payment-row space-y-3">
<div class="hms-grid three">
<label class="hms-field"><span>Payment method</span><select :name="'payments['+index+'][method]'" x-model="payment.method"><template x-if="!methods[payment.method]"><option :value="payment.method" x-text="payment.method + ' (no longer accepted)'"></option></template><template x-for="(label, key) in methods" :key="key"><option :value="key" :disabled="payments.some((item, other) => other !== index && item.method === key)" x-text="label"></option></template></select></label>
<label class="hms-field"><span x-text="payment.method === 'cash' ? 'Cash tendered' : 'Amount received'"></span><input type="number" step="0.01" min="0.01" required :name="'payments['+index+'][amount]'" x-model="payment.amount" inputmode="decimal"></label>
<div class="flex gap-2 items-end"><button type="button" class="hms-secondary" @click="exactPayment(payment)">Exact remaining amount</button><button type="button" class="hms-secondary" x-show="payments.length > 1" @click="payments.splice(index, 1)">Remove</button></div>
</div>
<div class="hms-grid" x-show="payment.method !== 'cash'">
<label class="hms-field"><span>Transaction reference</span><input :name="'payments['+index+'][reference]'" x-model="payment.reference" :disabled="payment.method === 'cash'" :required="payment.method !== 'cash'" maxlength="255"></label>
<label class="hms-check self-end pb-3"><input type="checkbox" :name="'payments['+index+'][confirmed]'" value="1" x-model="payment.confirmed" :disabled="payment.method === 'cash'" :required="payment.method !== 'cash'"> Payment completed</label>
</div>
</div></template>
<button type="button" class="hms-secondary" @click="addPayment()" x-show="payments.length < Object.keys(methods).length">+ Split payment</button>
<div class="hms-grid three hms-payment-totals" aria-live="polite"><div><small>Total · {{ $store->currency }}</small><strong class="hms-stat" x-text="money(total)"></strong></div><div><small>Still to collect</small><strong class="hms-stat" x-text="money(due)"></strong></div><div><small>Cash change</small><strong class="hms-stat" x-text="money(change)"></strong></div></div>
<p class="hms-error" x-show="noncashExcess > 0" x-cloak>Noncash payments exceed the sale total. Correct the entered amounts; only cash can produce change.</p>
<details @if(old('customer') || old('notes')) open @endif><summary>Customer reference & notes (optional)</summary><div class="hms-grid mt-3"><x-field name="customer" label="Customer / reference" maxlength="255"/><x-field name="notes" label="Sale notes" maxlength="2000"/></div></details>
<button class="hms-primary" :disabled="busy || lines.length === 0 || due > 0 || noncashExcess > 0 || total <= 0 || @js(!$session)"><span x-text="busy ? 'Posting… wait for confirmation' : 'Post paid sale'">Post paid sale</span></button>
<p class="hms-help">Only the posted record confirms the sale. Payment methods can be changed in Store profile.</p>
</div></form>@endif
<h2 id="transaction-history">Sales & returns</h2>@include('operations.history')
</x-store-screen>
