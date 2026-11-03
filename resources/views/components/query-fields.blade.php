@props(['except' => []])
@foreach(request()->except(array_merge(['export'], $except)) as $key => $value)
    @if(is_scalar($value) && $value !== '')<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif
@endforeach
