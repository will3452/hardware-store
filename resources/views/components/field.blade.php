@props(['name', 'label', 'type' => 'text', 'value' => '', 'required' => false, 'step' => null, 'useOld' => true])
<label class="hms-field"><span>{{ $label }}</span><input name="{{ $name }}" type="{{ $type }}" value="{{ $useOld ? old(preg_replace('/\[([^]]+)\]/', '.$1', $name), $value) : $value }}" @required($required) @if($step) step="{{ $step }}" @endif {{ $attributes }}></label>
