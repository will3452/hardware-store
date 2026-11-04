<x-store-screen :store="$store" :page="$page" :modules="$modules">
@if(!$readOnly)<p class="no-print"><a class="underline" href="{{ route('store.print-test', $store->id) }}">Printer setup & test print</a></p>@endif
<nav class="hms-section-links no-print" aria-label="On this page"><span>Jump to</span><a href="#transaction-history">Sales history</a></nav>
@if(!$readOnly && ($modules['sales'] ?? null) === 'enabled')
@if(!$session)<div class="hms-notice">Open your cash drawer before recording sales. <a class="underline" href="{{ \App\Support\StoreWorkspace::route('store.page', [$store->id, 'cash']) }}">Open cash session →</a></div>@endif
<form method="POST" action="{{ route('store.post', [$store->id, 'sale']) }}" x-data="hmsCart(@js(route('store.catalog', $store->id)), 'sale', @js($oldCart), @js(old('payments', [])), @js($store->acceptedPayments()))" x-init="settlement = @js(old('settlement', 'paid')); dueDate = @js(old('due_date', now()->timezone($store->timezone)->addDays(30)->toDateString()))" @customer-selected="dueDate = hmsDueDate(@js(now()->timezone($store->timezone)->toDateString()), $event.detail.term_days)" @submit="submit($event)" @pageshow.window="busy = false" class="space-y-5">
<x-operation-token/><input type="hidden" name="cash_session_id" value="{{ $session?->id }}">
@include('operations.cart', ['cartMode' => 'sale', 'cartTitle' => 'New sale'])
<div class="hms-card space-y-4">
@if(($modules['customers'] ?? null) === 'enabled')<x-customer-picker :store="$store"/>@endif
@if(($modules['credit'] ?? null) === 'enabled')
<div class="hms-grid"><label class="hms-field"><span>Payment arrangement</span><select name="settlement" x-model="settlement"><option value="paid">Pay in full</option><option value="credit">Charge balance to customer</option></select></label><label class="hms-field" x-show="settlement === 'credit'" x-cloak><span>Due date</span><input type="date" name="due_date" x-model="dueDate" value="{{ old('due_date', now()->timezone($store->timezone)->addDays(30)->toDateString()) }}" :required="settlement === 'credit'" :disabled="settlement !== 'credit'"></label></div><p class="hms-help" x-show="settlement === 'credit'" x-cloak>A selected customer must have credit enabled and sufficient remaining limit. Enter zero received for a wholly unpaid sale. Review the due date against their agreed terms.</p>
@endif
<h2>Payment</h2><p>Enter what you received. Split payments can use different methods; change comes from cash.</p>
<template x-for="(payment, index) in payments" :key="index"><div class="hms-payment-row space-y-3">
<div class="hms-grid three">
<label class="hms-field"><span>Payment method</span><select :name="'payments['+index+'][method]'" x-model="payment.method"><template x-if="!methods[payment.method]"><option :value="payment.method" x-text="payment.method + ' (no longer accepted)'"></option></template><template x-for="(label, key) in methods" :key="key"><option :value="key" :disabled="payments.some((item, other) => other !== index && item.method === key)" x-text="label"></option></template></select></label>
<label class="hms-field"><span x-text="payment.method === 'cash' ? 'Cash tendered' : 'Amount received'"></span><input type="number" step="0.01" :min="settlement === 'credit' ? 0 : 0.01" required :name="'payments['+index+'][amount]'" x-model="payment.amount" inputmode="decimal"></label>
<div class="flex gap-2 items-end"><button type="button" class="hms-secondary" @click="exactPayment(payment)">Exact remaining amount</button><button type="button" class="hms-secondary" x-show="payments.length > 1" @click="payments.splice(index, 1)">Remove</button></div>
</div>
<div class="hms-grid" x-show="payment.method !== 'cash'">
<label class="hms-field"><span>Transaction reference</span><input :name="'payments['+index+'][reference]'" x-model="payment.reference" :disabled="payment.method === 'cash'" :required="payment.method !== 'cash' && Number(payment.amount) > 0" maxlength="255"></label>
<label class="hms-check self-end pb-3"><input type="checkbox" :name="'payments['+index+'][confirmed]'" value="1" x-model="payment.confirmed" :disabled="payment.method === 'cash'" :required="payment.method !== 'cash' && Number(payment.amount) > 0"> Payment completed</label>
</div>
</div></template>
<button type="button" class="hms-secondary" @click="addPayment()" x-show="payments.length < Object.keys(methods).length">+ Split payment</button>
<div class="hms-grid three hms-payment-totals" aria-live="polite"><div><small>Total · {{ $store->currency }}</small><strong class="hms-stat" x-text="money(total)"></strong></div><div><small x-text="settlement === 'credit' ? 'Charge to customer' : 'Still to collect'">Still to collect</small><strong class="hms-stat" x-text="money(due)"></strong></div><div><small>Cash change</small><strong class="hms-stat" x-text="money(change)"></strong></div></div>
<p class="hms-error" x-show="noncashExcess > 0" x-cloak>Noncash payments exceed the sale total. Correct the entered amounts; only cash can produce change.</p>
<details @if(old('customer') || old('notes')) open @endif><summary>Customer reference & notes (optional)</summary><div class="hms-grid mt-3"><x-field name="customer" label="Customer / reference" maxlength="255"/><x-field name="notes" label="Sale notes" maxlength="2000"/></div></details>
<button class="hms-primary" :disabled="busy || scanning || lines.length === 0 || (settlement !== 'credit' && due > 0) || noncashExcess > 0 || total <= 0 || @js(!$session)"><span x-text="busy ? 'Posting… wait for confirmation' : (settlement === 'credit' ? 'Post sale & customer balance' : 'Post paid sale')">Post paid sale</span></button>
<p class="hms-help">Only the posted record confirms the sale. Payment methods can be changed in Store profile.</p>
</div></form>@endif
<h2 id="transaction-history">Sales & returns</h2>@include('operations.history')
</x-store-screen>
