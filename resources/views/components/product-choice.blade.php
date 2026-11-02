@props(['name', 'label', 'choices', 'value' => null, 'useOld' => false])
@php
    $value = Illuminate\Support\Str::squish($value ?? '');
    foreach ($choices as $option) {
        if (Illuminate\Support\Str::lower($option) === Illuminate\Support\Str::lower($value)) {
            $value = $option;
            break;
        }
    }
    $selected = $useOld ? old($name.'_choice', $value !== '' ? 'value:'.$value : 'none') : ($value !== '' ? 'value:'.$value : 'none');
@endphp
<div class="space-y-2" x-data="{ choice: @js($selected) }">
    <label class="hms-field"><span>{{ $label }} (optional)</span>
        <select name="{{ $name }}_choice" x-model="choice">
            <option value="none" @selected($selected === 'none')>No {{ strtolower($label) }}</option>
            @foreach($choices as $option)<option value="value:{{ $option }}" @selected($selected === 'value:'.$option)>{{ $option }}</option>@endforeach
            <option value="new" @selected($selected === 'new')>+ Add new {{ strtolower($label) }}</option>
        </select>
    </label>
    <div x-show="choice === 'new'">
        <x-field :name="$name" :label="'New '.strtolower($label).' name'" :use-old="$useOld" maxlength="80" x-bind:disabled="choice !== 'new'" x-bind:required="choice === 'new'" />
        <p class="hms-help mt-1">Saved to your store’s list for next time.</p>
    </div>
</div>
