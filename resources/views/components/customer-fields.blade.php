@props(['customer' => null, 'creditAvailable' => false])
@if($customer)<input type="hidden" name="customer_id" value="{{ $customer->id }}"><input type="hidden" name="revision" value="{{ $customer->revision }}">@endif
<div class="hms-grid three">
<x-field name="name" label="Customer / business name" :value="$customer?->name" maxlength="255" required/>
<x-field name="contact" label="Contact (optional)" :value="$customer?->contact" maxlength="255"/>
<x-field name="address" label="Address (optional)" :value="$customer?->address" maxlength="1000"/>
<label class="hms-field"><span>Status</span><select name="active"><option value="1" @selected(old('active', $customer?->active ?? true))>Active</option><option value="0" @selected(!old('active', $customer?->active ?? true))>Archived</option></select></label>
<x-field name="term_days" label="Default payment term (days)" :value="$customer?->term_days ?? 30" type="number" min="0" max="365" required/>
@if($creditAvailable)
<label class="hms-field"><span>Allow new credit</span><select name="credit_enabled"><option value="0" @selected(!old('credit_enabled', $customer?->credit_enabled ?? false))>No — paid purchases only</option><option value="1" @selected(old('credit_enabled', $customer?->credit_enabled ?? false))>Yes — within the reviewed limit</option></select></label>
<x-field name="credit_limit" label="Credit limit" :value="App\Support\Amounts::display($customer?->credit_limit_minor ?? 0)" type="number" min="0" step="0.01" max="999999999.99"/>
@else<p class="hms-help">Credit settings are retained. Enable the credit module to grant or change credit terms.</p>@endif
</div>
