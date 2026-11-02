@props(['form' => null])
@csrf
@if($form)<input type="hidden" name="_form" value="{{ $form }}">@endif
<input type="hidden" name="request_key" value="{{ !$form || old('_form') === $form ? old('request_key', (string) Illuminate\Support\Str::uuid()) : (string) Illuminate\Support\Str::uuid() }}">
