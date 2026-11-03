@props(['records', 'anchor', 'label', 'compact' => false])
<div class="hms-record-controls no-print">
    <p class="hms-help" role="status">{{ number_format($records->firstItem() ?? 0) }}–{{ number_format($records->lastItem() ?? 0) }} of {{ number_format($records->total()) }} {{ strtolower($label) }}</p>
    @if(!$compact)
    <form method="GET" action="{{ request()->url() }}#{{ $anchor }}" class="hms-record-size">
        <x-query-fields :except="array_merge(['per_page', 'page'], array_filter(array_keys(request()->query()), fn($key) => str_ends_with($key, '_page')))"/>
        <label>Rows <select name="per_page" aria-label="Rows per page for {{ strtolower($label) }}">@foreach([10, 25, 50] as $size)<option value="{{ $size }}" @selected($records->perPage() === $size)>{{ $size }}</option>@endforeach</select></label>
        <button class="hms-secondary">Apply</button>
    </form>
    @endif
    <nav aria-label="{{ $label }} {{ $compact ? 'bottom' : 'top' }} pagination" class="hms-record-pages">
        @if($records->onFirstPage())<span class="hms-secondary" aria-disabled="true">← Previous</span>@else<a class="hms-secondary" rel="prev" href="{{ $records->previousPageUrl() }}">← Previous</a>@endif
        <span class="hms-help">Page {{ $records->currentPage() }} of {{ $records->lastPage() }}</span>
        @if($records->hasMorePages())<a class="hms-secondary" rel="next" href="{{ $records->nextPageUrl() }}">Next →</a>@else<span class="hms-secondary" aria-disabled="true">Next →</span>@endif
    </nav>
    @if(!$compact && $records->hasPages())
    <form method="GET" action="{{ request()->url() }}#{{ $anchor }}" class="hms-record-jump">
        <x-query-fields :except="[$records->getPageName()]"/>
        <label>Go to <input type="number" name="{{ $records->getPageName() }}" value="{{ $records->currentPage() }}" min="1" max="{{ $records->lastPage() }}" required aria-label="{{ $label }} page number"></label>
        <button class="hms-secondary">Go</button>
    </form>
    @endif
</div>
