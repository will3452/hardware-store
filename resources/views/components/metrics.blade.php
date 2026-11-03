@props(['metrics', 'currency' => 'PHP'])
<div class="hms-stats">
@foreach (['net_sales' => 'Recorded sales after returns', 'gross_profit' => 'Gross profit estimate', 'expenses' => 'Paid operating expenses', 'operating_estimate' => 'Operating profit estimate', 'collections' => 'Customer payments received', 'receivables' => 'Outstanding customer balances'] as $key => $label)
<div class="hms-card pyesio-metric" data-metric="{{ $key }}"><p>{{ $label }}</p><strong class="hms-stat">{{ $currency }} {{ number_format(($metrics[$key] ?? 0) / 100, 2) }}</strong></div>
@endforeach
</div>
<p class="hms-help">Management estimates based on recorded amounts. Tax is not separated. Unpaid bills, depreciation, financing costs, and income tax are excluded. Stock gains and cash variances are shown separately.</p>
