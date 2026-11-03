<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\User;
use App\Support\Amounts;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductCatalog
{
    /** @var array<string, array{label: string, fractional: bool}> */
    public const UNITS = [
        'piece' => ['label' => 'Piece', 'fractional' => false],
        'pair' => ['label' => 'Pair', 'fractional' => false],
        'set' => ['label' => 'Set', 'fractional' => false],
        'box' => ['label' => 'Box', 'fractional' => false],
        'pack' => ['label' => 'Pack', 'fractional' => false],
        'bag' => ['label' => 'Bag', 'fractional' => false],
        'roll' => ['label' => 'Roll', 'fractional' => false],
        'sheet' => ['label' => 'Sheet', 'fractional' => false],
        'meter' => ['label' => 'Meter (m)', 'fractional' => true],
        'foot' => ['label' => 'Foot (ft)', 'fractional' => true],
        'kg' => ['label' => 'Kilogram (kg)', 'fractional' => true],
        'liter' => ['label' => 'Liter (L)', 'fractional' => true],
        'sqm' => ['label' => 'Square meter (m²)', 'fractional' => true],
    ];

    public const CATEGORIES = ['Building materials', 'Electrical', 'Fasteners', 'Hand tools', 'Paint & supplies', 'Plumbing', 'Power tools', 'Safety equipment'];

    /** @return list<string> */
    public function choices(string $field): array
    {
        $values = Product::whereNotNull($field)->distinct()->orderBy($field)->pluck($field)->all();
        if ($field === 'category') {
            $values = array_merge($values, self::CATEGORIES);
        }

        return array_values(collect($values)->map(fn ($value) => Str::squish($value))->filter(fn ($value) => $value !== '')
            ->unique(fn ($value) => Str::lower($value))->sort(SORT_NATURAL | SORT_FLAG_CASE)->all());
    }

    /** @param array<string, mixed> $data */
    public function save(User $user, array $data): Product
    {
        return DB::transaction(function () use ($user, $data): Product {
            $access = app(StoreAccess::class);
            $store = $access->lock($user);
            $access->module('catalog');
            $product = ! empty($data['product_id']) ? Product::whereKey($data['product_id'])->firstOrFail() : new Product;
            $template = null;
            if (! empty($data['template_key'])) {
                $this->check(! $product->exists || $product->template_key === $data['template_key'], 'template_key', 'Keep the existing product type. Create a new product for a different type.');
                $template = app(ProductTemplates::class)->normalize($data);
                $data['name'] = $template['name'];
            } elseif ($product->exists && $product->template_key) {
                // Older open forms may omit new fields; preserve the stored specifications.
                $template = ['template_key' => $product->template_key, 'template_version' => $product->template_version, 'specifications' => $product->specifications ?? [], 'name' => $product->name];
                $data['name'] = $product->name;
            }
            $name = Str::squish($data['name'] ?? '');
            $this->check($name !== '', 'name', 'Enter the product name and size or variant.');
            $baseUnit = $data['base_unit'];
            if ($product->exists) {
                StoreAccess::check($product->base_unit === $baseUnit && (bool) $product->fractional === (bool) ($data['fractional'] ?? false), 'Base units and fractional settings are fixed. Create a new SKU for a different base unit.');
                $fractional = $product->fractional;
            } else {
                $this->check(isset(self::UNITS[$baseUnit]), 'base_unit', 'Choose a selling unit from the list.');
                $fractional = self::UNITS[$baseUnit]['fractional'];
            }
            $category = $this->label($data, 'category');
            $brand = $this->label($data, 'brand');
            if ($template) {
                $fingerprint = app(ProductTemplates::class)->fingerprint($template['template_key'], $template['specifications'], $brand, $baseUnit);
                $this->check(! Product::where('template_fingerprint', $fingerprint)->when($product->exists, fn ($q) => $q->whereKeyNot($product->id))->exists(), 'name', 'This product specification, brand, and unit are already registered, possibly archived. Open the existing product.');
                $product->fill($template + ['template_fingerprint' => $fingerprint]);
                $category ??= ProductTemplates::definitions()[$template['template_key']]['category'];
            }
            $sku = trim($data['sku'] ?? '');
            if ($sku === '') {
                $this->check(! $product->exists, 'sku', 'Keep the existing SKU or enter a replacement.');
                $number = Product::count() + 1;
                do {
                    $sku = 'P-'.str_pad((string) $number++, 6, '0', STR_PAD_LEFT);
                } while (Product::where('sku', $sku)->exists());
            }
            $barcode = trim($data['barcode'] ?? '');
            $barcode = $barcode === '' ? null : $barcode;
            $this->check(! Product::where('sku', $sku)->when($product->exists, fn ($q) => $q->whereKeyNot($product->id))->exists(), 'sku', 'That SKU is already in use. Leave it blank to generate one for a new product.');
            $this->check($barcode === null || ! Product::where('barcode', $barcode)->when($product->exists, fn ($q) => $q->whereKeyNot($product->id))->exists(), 'barcode', 'That barcode is already registered. Find the existing product before adding another.');
            if (! $product->exists || $name !== $product->name || $brand !== $product->brand) {
                $duplicate = Product::where('name', $name)->where('base_unit', $baseUnit)
                    ->when($product->exists, fn ($q) => $q->whereKeyNot($product->id))->get()
                    ->first(fn ($item) => Str::lower(Str::squish($item->brand ?? '')) === Str::lower($brand ?? ''));
                $this->check($duplicate === null, 'name', 'This name, brand, and unit are already registered'.($duplicate ? ' as '.$duplicate->sku : '').'. Edit that product, or include the different size or variant in the name.');
            }
            $reorder = Amounts::parse($data['reorder'] ?? '0', 6);
            $this->check($fractional || $reorder % 1000000 === 0, 'reorder', 'Use a whole-number low-stock alert for this selling unit.');
            $product->fill(['sku' => $sku, 'barcode' => $barcode, 'name' => $name, 'category' => $category, 'brand' => $brand, 'base_unit' => $baseUnit,
                'price_minor' => Amounts::parse($data['price']), 'reorder_micro' => $reorder, 'fractional' => $fractional, 'active' => $data['active'] ?? true]);
            $product->save();
            ProductUnit::firstOrCreate(['product_id' => $product->id, 'label' => $product->base_unit], ['factor_micro' => 1000000]);
            if (! empty($data['pack_label']) || ! empty($data['pack_factor'])) {
                $this->check(empty($data['product_id']), 'pack_label', 'Add another conversion from the product details.');
                $label = Str::squish($data['pack_label'] ?? '');
                $factor = Amounts::parse($data['pack_factor'] ?? '0', 6);
                $this->check($label !== '' && $label !== $baseUnit && $factor > 0 && ($fractional || $factor % 1000000 === 0), 'pack_factor', 'Enter a different pack label and a positive quantity compatible with the selling unit.');
                ProductUnit::create(['product_id' => $product->id, 'label' => $label, 'factor_micro' => $factor]);
            }
            StoreAccess::audit($user, $store->id, 'product.saved', $product->id, ['sku' => $product->sku, 'active' => $product->active]);

            return $product;
        }, 3);
    }

    /** @param array<string, mixed> $data */
    private function label(array $data, string $field): ?string
    {
        $choices = $this->choices($field);
        $choice = $data[$field.'_choice'] ?? null;
        $value = Str::squish($data[$field] ?? '');
        if ($choice !== null) {
            if (str_starts_with($choice, 'value:')) {
                $value = substr($choice, 6);
                $this->check(in_array($value, $choices, true), $field, 'Choose an existing '.$field.' or select “Add new”.');
            } elseif ($choice === 'new') {
                $this->check($value !== '', $field, 'Enter the new '.$field.' name.');
            } else {
                $this->check($choice === 'none', $field, 'Choose a valid '.$field.'.');
                $value = '';
            }
        }
        foreach ($choices as $existing) {
            if (Str::lower($existing) === Str::lower($value)) {
                return $existing;
            }
        }

        return $value === '' ? null : $value;
    }

    private function check(bool $condition, string $field, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages([$field => $message]);
        }
    }
}
