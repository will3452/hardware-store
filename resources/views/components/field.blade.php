@props(['name', 'label', 'type' => 'text', 'value' => '', 'required' => false, 'step' => null])
<label class="hms-field"><span>{{ $label }}</span><input name="{{ $name }}" type="{{ $type }}" value="{{ old(preg_replace('/\[([^]]+)\]/', '.$1', $name), $value) }}" @required($required) @if($step) step="{{ $step }}" @endif {{ $attributes }}></label>
