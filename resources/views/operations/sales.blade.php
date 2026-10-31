<x-store-screen :store="$store" :page="$page" :modules="$modules">
@if(($modules['sales'] ?? null) === 'enabled')
@if(!$session)<div class="hms-notice">Open your cash drawer before recording sales. <a class="underline" href="{{ route('store.page', [$store->id, 'cash']) }}">Open cash session →</a></div>@endif
<form method="POST" action="{{ route('store.post', [$store->id, 'sale']) }}" x-data="hmsCart(@js(route('store.catalog', $store->id)), 'sale', @js($oldCart))" @submit="busy = true" class="space-y-5"><x-operation-token/><input type="hidden" name="cash_session_id" value="{{ $session?->id }}">
@include('operations.cart', ['cartMode' => 'sale'])
<div class="hms-card space-y-4"><h2>Payment</h2><div class="hms-grid"><x-field name="customer" label="Customer / reference (optional)" maxlength="255"/><x-field name="notes" label="Sale notes (optional)" maxlength="2000"/></div>
<p>Enter cash tendered and any completed noncash payments. Cash change is computed at posting. Noncash payments require a reference and confirmation.</p>
@foreach(['cash' => 'Cash tendered', 'ewallet' => 'E-wallet received', 'bank' => 'Bank transfer received', 'card' => 'Card payment received'] as $method => $label)
@php($index = $loop->index)
<div class="hms-grid three"><input type="hidden" name="payments[{{ $index }}][method]" value="{{ $method }}"><x-field :name="'payments['.$index.'][amount]'" :label="$label" type="number" step="0.01" min="0" value="0" required/>
@if($method !== 'cash')<x-field :name="'payments['.$index.'][reference]'" label="Transaction reference" maxlength="255"/><label class="hms-check self-end pb-3"><input type="checkbox" name="payments[{{ $index }}][confirmed]" value="1" @checked(old('payments.'.$index.'.confirmed'))> Payment completed</label>@endif</div>@endforeach
<button class="hms-primary" :disabled="busy || lines.length === 0 || @js(!$session)"><span x-text="busy ? 'Posting… wait for confirmation' : 'Post paid sale'"></span></button></div></form>@endif
<h2>Sales & returns</h2>@include('operations.history')
</x-store-screen>
