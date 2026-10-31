<div class="hms-grid">
<label class="hms-field"><span>Payment / refund method</span><select name="method">@foreach(['cash' => 'Cash', 'ewallet' => 'E-wallet', 'bank' => 'Bank transfer', 'card' => 'Card'] as $value => $text)<option value="{{ $value }}" @selected(old('method') === $value)>{{ $text }}</option>@endforeach</select></label>
<x-field name="reference" label="Noncash transaction reference" maxlength="255" />
</div>
<label class="hms-check"><input type="checkbox" name="confirmed" value="1" @checked(old('confirmed'))> I confirm the noncash payment/refund has completed.</label>
