@props(['records', 'id', 'label'])
@php($records->fragment($id))
<section id="{{ $id }}" class="hms-record-list" aria-label="{{ $label }}">
    @if($records->currentPage() > $records->lastPage())<p class="hms-notice">This page has no records. <a class="underline" href="{{ $records->url(1) }}">Go to the first page</a>.</p>@endif
    <x-record-controls :records="$records" :anchor="$id" :label="$label"/>
    {{ $slot }}
    @if($records->hasPages())<x-record-controls :records="$records" :anchor="$id" :label="$label" compact/>@endif
</section>
