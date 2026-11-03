<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class ProductTemplates
{
    /** @return array<string, array<string, mixed>> */
    public static function definitions(): array
    {
        $text = fn (string $label, array $choices = [], bool $required = true): array => compact('label', 'choices', 'required') + ['select' => false];
        $select = fn (string $label, array $choices): array => compact('label', 'choices') + ['required' => true, 'select' => true];

        return [
            'wire' => ['label' => 'Electrical wire', 'category' => 'Electrical', 'units' => ['meter', 'foot', 'roll'], 'fields' => [
                'type' => $text('Wire type', ['THHN', 'THW', 'Flat cord']), 'size' => $text('Wire size'), 'size_unit' => $select('Size system', ['mm²', 'AWG']), 'color' => $text('Color', ['Red', 'Black', 'White', 'Green', 'Blue', 'Yellow'], false),
            ]],
            'pipe' => ['label' => 'Pipe or fitting', 'category' => 'Plumbing', 'units' => ['piece', 'meter', 'foot'], 'fields' => [
                'type' => $text('Item type', ['Pipe', 'Elbow', 'Tee', 'Coupling', 'Reducer']), 'material' => $text('Material', ['PVC', 'PPR', 'GI', 'Copper']), 'size' => $text('Nominal size'), 'size_unit' => $select('Size unit', ['mm', 'inch']), 'class' => $text('Class / schedule', [], false), 'length' => $text('Length with unit (for pipe)', [], false),
            ]],
            'fastener' => ['label' => 'Fastener', 'category' => 'Fasteners', 'units' => ['piece', 'box', 'pack', 'kg'], 'fields' => [
                'type' => $text('Fastener type', ['Screw', 'Bolt', 'Nut', 'Nail', 'Washer']), 'diameter' => $text('Diameter / designation'), 'size_unit' => $select('Diameter system', ['mm', 'inch', 'gauge', 'designation']), 'length' => $text('Length with unit (if applicable)', [], false), 'material' => $text('Material / finish', ['Steel', 'Stainless steel', 'Galvanized', 'Brass']),
            ]],
            'steel_lumber' => ['label' => 'Steel or lumber', 'category' => 'Building materials', 'units' => ['piece', 'meter', 'foot'], 'fields' => [
                'material' => $text('Material', ['Steel', 'Lumber']), 'type' => $text('Profile / type', ['Angle bar', 'Flat bar', 'Rebar', 'Square tube', 'Board']), 'dimensions' => $text('Section dimensions with units'), 'length' => $text('Length with unit'), 'grade' => $text('Grade / species', [], false),
            ]],
            'paint' => ['label' => 'Paint', 'category' => 'Paint & supplies', 'units' => ['piece', 'liter'], 'fields' => [
                'type' => $text('Paint type', ['Latex', 'Enamel', 'Primer', 'Epoxy']), 'finish' => $text('Finish', ['Flat', 'Semi-gloss', 'Gloss']), 'color' => $text('Color / code'), 'size' => $text('Container size'), 'size_unit' => $select('Container unit', ['mL', 'L', 'US gal', 'Imperial gal']),
            ]],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    public function choices(): array
    {
        $definitions = self::definitions();
        foreach (Product::whereNotNull('template_key')->orderBy('updated_at', 'desc')->limit(500)->get(['template_key', 'specifications']) as $product) {
            foreach ($product->specifications ?? [] as $field => $value) {
                if ($value !== '' && isset($definitions[$product->template_key]['fields'][$field]) && ! $definitions[$product->template_key]['fields'][$field]['select']) {
                    $choices = $definitions[$product->template_key]['fields'][$field]['choices'];
                    $definitions[$product->template_key]['fields'][$field]['choices'] = array_slice(array_values(array_unique([...$choices, $value])), 0, 30);
                }
            }
        }

        return $definitions;
    }

    /** @param array<string, mixed> $data
     * @return array{template_key: string, template_version: int, specifications: array<string, string>, name: string}
     */
    public function normalize(array $data): array
    {
        $definitions = self::definitions();
        Validator::make($data, ['template_key' => ['required', 'string', Rule::in(array_keys($definitions))]])->validate();
        $key = $data['template_key'];
        $definition = $definitions[$key];
        $rules = ['specifications' => ['required', 'array:'.implode(',', array_keys($definition['fields']))], 'base_unit' => ['required', Rule::in($definition['units'])]];
        foreach ($definition['fields'] as $field => $options) {
            $rules['specifications.'.$field] = [$options['required'] ? 'required' : 'nullable', 'string', 'max:50'];
            if ($options['select']) {
                $rules['specifications.'.$field][] = Rule::in($options['choices']);
            }
        }
        Validator::make($data, $rules)->validate();
        $specs = [];
        foreach ($definition['fields'] as $field => $options) {
            $value = Str::squish($data['specifications'][$field] ?? '');
            if ($options['required'] && $value === '') {
                throw ValidationException::withMessages(['specifications.'.$field => 'Enter '.$options['label'].'.']);
            }
            if (preg_match('/^\d+(\.\d+)?$/D', $value)) {
                $value = ltrim($value, '0');
                $value = str_contains($value, '.') ? rtrim(rtrim($value, '0'), '.') : $value;
                $value = $value === '' ? '0' : (str_starts_with($value, '.') ? '0'.$value : $value);
            }
            $specs[$field] = $value;
        }
        $name = implode(' · ', array_filter($specs, fn ($value) => $value !== ''));
        Validator::make(['name' => $name], ['name' => ['required', 'string', 'max:255']])->validate();

        return ['template_key' => $key, 'template_version' => 1, 'specifications' => $specs, 'name' => $name];
    }

    /** @param array<string, string> $specifications */
    public function fingerprint(string $key, array $specifications, ?string $brand, string $unit): string
    {
        return hash('sha256', Str::lower(json_encode([$key, $specifications, $brand ?? '', $unit], JSON_THROW_ON_ERROR)));
    }
}
