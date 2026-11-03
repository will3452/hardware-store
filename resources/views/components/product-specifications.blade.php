@props(['templates', 'selected' => '', 'specs' => [], 'useOld' => false, 'editing' => false, 'prefix' => 'new'])
@if(!$editing)
<label class="hms-field"><span>Product type</span><select name="template_key" x-model="template" @change="changeTemplate()"><option value="">General product</option>@foreach($templates as $key => $definition)<option value="{{ $key }}" @selected($selected === $key)>{{ $definition['label'] }}</option>@endforeach</select></label>
@else
<input type="hidden" name="template_key" value="{{ $selected }}">
@endif
@foreach($templates as $key => $definition)
<fieldset class="space-y-3" x-show="template === @js($key)" @if($selected !== $key) x-cloak @endif>
    <legend class="font-semibold">{{ $definition['label'] }} specifications</legend>
    <div class="hms-grid three">
    @foreach($definition['fields'] as $field => $options)
        <label class="hms-field"><span>{{ $options['label'] }}{{ $options['required'] ? '' : ' (optional)' }}</span>
        @if($options['select'])
            <select name="specifications[{{ $field }}]" x-model="specs.{{ $field }}" :disabled="template !== @js($key)" :required="template === @js($key)"><option value="">Choose…</option>@foreach($options['choices'] as $choice)<option value="{{ $choice }}">{{ $choice }}</option>@endforeach</select>
        @else
            <input name="specifications[{{ $field }}]" x-model="specs.{{ $field }}" :disabled="template !== @js($key)" :required="template === @js($key) && @js($options['required'])" maxlength="50" @if($options['choices']) list="spec-{{ $prefix }}-{{ $key }}-{{ $field }}" @endif>
            @if($options['choices'])<datalist id="spec-{{ $prefix }}-{{ $key }}-{{ $field }}">@foreach($options['choices'] as $choice)<option value="{{ $choice }}"></option>@endforeach</datalist>@endif
        @endif
        </label>
    @endforeach
    </div>
    <p class="hms-help">Use the specifications printed on the item. Size systems and pack contents are not interchangeable.</p>
</fieldset>
@endforeach
