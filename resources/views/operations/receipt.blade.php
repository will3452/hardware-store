<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $sample ? 'Test print' : 'Sales record '.$doc->id }} · Pyesio</title><link rel="stylesheet" href="{{ asset('receipt.css') }}">@vite('resources/js/app.js')</head>
<body class="paper-{{ $paper }}">
<header class="print-controls no-print"><h1>Print preview{{ $sample ? ' · Test print' : '' }}</h1>
<form method="get"><label>Paper <select name="paper"><option value="document" @selected($paper === 'document')>Document / A4</option><option value="58" @selected($paper === '58')>58 mm roll</option><option value="80" @selected($paper === '80')>80 mm roll</option></select></label><label><input type="checkbox" name="copy" value="1" @checked($copy)> Label as COPY</label><button>Update preview</button></form>
<button type="button" onclick="hmsPrint()">Open print dialog</button> <a href="{{ $doc ? App\Support\StoreWorkspace::route('store.document', [$store->id, $doc->id]) : route('store.page', [$store->id, 'sales']) }}">Back to {{ $doc ? 'record' : 'POS' }}</a>
<p>Select matching paper in your printer driver, 100% scale, no browser headers/footers, and minimum margins. Browser printing or PDF works without a receipt printer.</p><p id="print-status" role="status">Preview only. Opening or cancelling printing does not change the sale.</p></header>
<main class="receipt">
@if($sample)<p class="stamp">TEST PRINT · NOT A SALE</p>@endif
@if($copy)<p class="stamp">COPY · REPRINT</p>@endif
<h2>{{ $doc?->metadata['store_name'] ?? $store->display_name }}</h2><p>{{ $doc?->metadata['store_address'] ?? $store->address_line_1.', '.$store->city }}</p>
<p><strong>{{ $doc ? strtoupper($doc->kind).' RECORD' : 'SAMPLE SALES RECORD' }}</strong><br><span class="reference">{{ $doc?->id ?? 'TEST-000000000000000000000000' }}</span><br>{{ ($doc?->posted_at ?? now())->timezone($store->timezone)->format('M j, Y H:i:s') }} · {{ $store->timezone }}</p>
@if($doc?->original_id)<p>Correction of <span class="reference">{{ $doc->original_id }}</span></p>@endif
@if($doc && !empty($doc->metadata['customer']))<p>Customer / reference: {{ $doc->metadata['customer'] }}</p>@endif
<div class="receipt-items">@foreach($lines as $line)<section class="receipt-item"><strong>{{ $line->name }}</strong><small>{{ $line->sku }}</small><div class="receipt-row"><span>{{ App\Support\Amounts::quantity(App\Support\Amounts::ratio($line->quantity_micro, 1000000, $line->factor_micro)) }} {{ $line->unit_label }} × {{ number_format($line->price_minor / 100, 2) }}</span><strong>{{ number_format($line->total_minor / 100, 2) }}</strong></div>
@if($line->factor_micro !== 1000000)<small>{{ App\Support\Amounts::quantity($line->quantity_micro) }} {{ $line->base_unit }} total</small>@endif
@if($line->discount_minor)<small>Line discount: {{ number_format($line->discount_minor / 100, 2) }}</small>@endif</section>@endforeach</div>
<div class="receipt-row total"><strong>Total · {{ $doc?->metadata['currency'] ?? $store->currency }}</strong><strong>{{ number_format(($doc?->total_minor ?? 23000) / 100, 2) }}</strong></div>
@foreach($payments as $payment)<div class="receipt-row"><span>{{ ucfirst($payment->method) }} applied</span><span>{{ number_format($payment->amount_minor / 100, 2) }}</span></div>@endforeach
@if(!$doc || $doc->kind === 'sale')<div class="receipt-row"><span>Cash tendered</span><span>{{ number_format(($doc?->metadata['cash_tendered_minor'] ?? ($sample ? 25000 : 0)) / 100, 2) }}</span></div><div class="receipt-row"><span>Change</span><span>{{ number_format(($doc?->metadata['change_minor'] ?? ($sample ? 2000 : 0)) / 100, 2) }}</span></div>@endif
@if($doc?->customer_id && $doc->kind === 'sale')<p>Due date: {{ $doc->due_date ?: 'Paid at checkout' }}<br>Outstanding now: {{ number_format($balance / 100, 2) }}<br><small>Balance as of this preview; collections may change it.</small></p>@endif
@if($doc && array_key_exists('debt_reduction_minor', $doc->metadata))<p>Debt reduced: {{ number_format($doc->metadata['debt_reduction_minor'] / 100, 2) }}<br>Money refunded: {{ number_format($doc->metadata['refund_minor'] / 100, 2) }}</p>@endif
@if($doc && !empty($doc->metadata['notes']))<p class="notes">{{ $doc->metadata['notes'] }}</p>@endif
<footer>Internal sales record.<br>Not a BIR-registered invoice.<br>Tax calculation is not configured.</footer>
</main></body></html>
