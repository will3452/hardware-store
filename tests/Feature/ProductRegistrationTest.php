<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Store;
use App\Models\StoreModule;
use App\Models\User;
use App\Support\Tenancy\StoreContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->store = Store::factory()->create();
        $owner = User::factory()->create();
        $owner->memberships()->create(['store_id' => $this->store->id, 'role' => 'owner', 'status' => 'active']);
        app(StoreContext::class)->set($this->store);
        StoreModule::create(['module_key' => 'catalog', 'mode' => 'enabled']);
        app(StoreContext::class)->clear();
        $this->actingAs($owner);
    }

    /** @return array<string, string> */
    private function input(): array
    {
        return ['_form' => 'product-new', 'name' => 'Claw hammer, 16 oz', 'base_unit' => 'piece', 'price' => '285.50'];
    }

    public function test_three_fields_create_a_product_and_automatic_sku_skips_existing_codes(): void
    {
        $url = route('store.products.save', $this->store);
        $this->post($url, $this->input() + ['sku' => 'P-000002'])->assertSessionHasNoErrors();
        $this->post($url, array_replace($this->input(), ['name' => 'Hammer, 20 oz']))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('products', ['store_id' => $this->store->id, 'sku' => 'P-000003', 'price_minor' => 28550, 'reorder_micro' => 0, 'fractional' => false]);
        $this->assertDatabaseCount('product_units', 2);
    }

    public function test_unit_selection_controls_precision_on_the_server(): void
    {
        $url = route('store.products.save', $this->store);
        $this->post($url, array_replace($this->input(), ['base_unit' => 'kg', 'fractional' => '0', 'reorder' => '2.5']))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('products', ['base_unit' => 'kg', 'fractional' => true, 'reorder_micro' => 2500000]);
        $this->post($url, $this->input() + ['fractional' => '1', 'reorder' => '0.5'])->assertSessionHasErrors('reorder');
        $this->post($url, array_replace($this->input(), ['base_unit' => 'peice']))->assertSessionHasErrors('base_unit');
        $this->assertDatabaseCount('products', 1);
    }

    public function test_labels_are_reused_despite_case_and_whitespace_and_require_explicit_new_selection(): void
    {
        $url = route('store.products.save', $this->store);
        $this->post($url, $this->input() + ['category_choice' => 'new', 'category' => '  HAND   tools ', 'brand_choice' => 'new', 'brand' => 'Acme'])->assertSessionHasNoErrors();
        $this->post($url, array_replace($this->input(), ['name' => 'Hammer, 20 oz', 'category_choice' => 'value:Hand tools', 'brand_choice' => 'new', 'brand' => ' acme ']))->assertSessionHasNoErrors();
        $this->assertSame(['Hand tools'], DB::table('products')->distinct()->pluck('category')->all());
        $this->assertSame(['Acme'], DB::table('products')->distinct()->pluck('brand')->all());
        $this->post($url, $this->input() + ['brand_choice' => 'new'])->assertSessionHasErrors('brand');
        $this->post($url, $this->input() + ['brand_choice' => 'value:Unknown'])->assertSessionHasErrors('brand');
    }

    public function test_duplicate_products_and_barcodes_are_rejected_without_another_write(): void
    {
        $url = route('store.products.save', $this->store);
        $this->post($url, $this->input() + ['brand' => 'Acme', 'barcode' => '001234'])->assertSessionHasNoErrors();
        $this->post($url, array_replace($this->input(), ['name' => ' claw   HAMMER, 16 oz ', 'brand' => ' acme ']))->assertSessionHasErrors('name');
        $this->post($url, array_replace($this->input(), ['name' => 'Different product', 'barcode' => '001234']))->assertSessionHasErrors('barcode');
        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseCount('product_units', 1);
        $this->assertSame(1, DB::table('audit_events')->where('action', 'product.saved')->count());
        $this->post($url, array_replace($this->input(), ['name' => 'Claw hammer, 20 oz', 'brand' => 'Acme', 'barcode' => '0']))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('products', ['barcode' => '0']);
    }

    public function test_archived_duplicates_are_still_detected(): void
    {
        $url = route('store.products.save', $this->store);
        $this->post($url, $this->input() + ['active' => '0'])->assertSessionHasNoErrors();
        $this->post($url, $this->input())->assertSessionHasErrors('name');
        $this->assertDatabaseCount('products', 1);
    }

    public function test_choices_and_duplicate_checks_are_scoped_to_the_current_store(): void
    {
        $other = Store::factory()->create();
        app(StoreContext::class)->set($other);
        Product::create(['sku' => 'P-000001', 'name' => $this->input()['name'], 'base_unit' => 'piece', 'price_minor' => 100, 'brand' => 'Private other brand', 'category' => 'Private other category']);
        app(StoreContext::class)->clear();
        $this->get(route('store.page', [$this->store, 'products']))->assertOk()->assertDontSee('Private other brand')->assertDontSee('Private other category');
        $this->post(route('store.products.save', $this->store), $this->input())->assertSessionHasNoErrors();
        $this->assertDatabaseHas('products', ['store_id' => $this->store->id, 'sku' => 'P-000001']);
    }

    public function test_save_another_preserves_only_reusable_choices_and_clears_product_specific_fields(): void
    {
        $this->post(route('store.products.save', $this->store), $this->input() + ['category' => 'Hand tools', 'brand' => 'Acme', 'sku' => 'CUSTOM', 'barcode' => '12345', 'reorder' => '10', 'save_action' => 'another'])
            ->assertSessionHasNoErrors()->assertRedirect(route('store.page', [$this->store, 'products']))
            ->assertSessionHas('product_next', ['category' => 'Hand tools', 'brand' => 'Acme', 'base_unit' => 'piece']);
        $response = $this->get(route('store.page', [$this->store, 'products']))->assertOk();
        $dom = new \DOMDocument;
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);
        foreach (['name', 'price', 'sku', 'barcode'] as $field) {
            $this->assertSame('', $xpath->evaluate('string(//details[@id="add-product"]//input[@name="'.$field.'"]/@value)'));
        }
        $this->assertSame('value:Acme', $xpath->evaluate('string(//details[@id="add-product"]//select[@name="brand_choice"]/option[@selected]/@value)'));
    }

    public function test_failed_creation_keeps_its_inputs_without_overwriting_an_existing_edit_form(): void
    {
        $this->post(route('store.products.save', $this->store), $this->input())->assertSessionHasNoErrors();
        $product = DB::table('products')->first();
        $this->from(route('store.page', [$this->store, 'products']))->post(route('store.products.save', $this->store), array_replace($this->input(), ['name' => 'Unsaved nails', 'price' => '-10']))->assertSessionHasErrors('price');
        $response = $this->get(route('store.page', [$this->store, 'products']))->assertOk();
        $dom = new \DOMDocument;
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);
        $this->assertSame('Unsaved nails', $xpath->evaluate('string(//details[@id="add-product"]//input[@name="name"]/@value)'));
        $this->assertSame('Claw hammer, 16 oz', $xpath->evaluate('string(//dialog[@id="product-'.$product->id.'"]//input[@name="name"]/@value)'));
        $this->assertSame(1, $xpath->query('//details[@id="add-product"][@open]')->length);
    }

    public function test_existing_custom_units_and_stock_are_preserved_and_cannot_be_changed(): void
    {
        app(StoreContext::class)->set($this->store);
        $product = Product::create(['sku' => 'LEGACY', 'name' => 'Legacy wire', 'base_unit' => 'custom length', 'price_minor' => 100, 'fractional' => true, 'quantity_micro' => 1200000, 'value_micro' => 6000000]);
        app(StoreContext::class)->clear();
        $input = array_replace($this->input(), ['product_id' => $product->id, 'sku' => 'LEGACY', 'name' => 'Legacy wire', 'base_unit' => 'custom length', 'fractional' => '1']);
        $this->post(route('store.products.save', $this->store), $input)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('products', ['id' => $product->id, 'base_unit' => 'custom length', 'fractional' => true, 'quantity_micro' => 1200000, 'value_micro' => 6000000]);
        $this->post(route('store.products.save', $this->store), array_replace($input, ['base_unit' => 'meter']))->assertSessionHasErrors();
    }

    public function test_restricted_catalog_cannot_create_products(): void
    {
        DB::table('store_modules')->where('store_id', $this->store->id)->update(['mode' => 'restricted']);
        $this->post(route('store.products.save', $this->store), $this->input())->assertForbidden();
        $this->assertDatabaseCount('products', 0);
    }

    public function test_guided_product_saves_specifications_and_conversion_without_creating_stock(): void
    {
        $input = $this->wireInput();
        $this->post(route('store.products.save', $this->store), $input)->assertSessionHasNoErrors();
        $product = DB::table('products')->first();
        $this->assertSame('THHN · 2 · mm² · Red', $product->name);
        $this->assertSame('wire', $product->template_key);
        $this->assertEquals(0, $product->quantity_micro);
        $this->assertDatabaseHas('product_units', ['product_id' => $product->id, 'label' => 'roll of 100 m', 'factor_micro' => 100000000]);
        $this->get(route('store.page', [$this->store, 'products']))->assertOk()->assertSee('Electrical wire')->assertSee('THHN');
        $this->post(route('store.products.save', $this->store), $input)->assertSessionHasErrors('name');
        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseCount('product_units', 2);
        $input['specifications']['size'] = '3.5';
        $this->post(route('store.products.save', $this->store), $input)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('products', 2);
    }

    public function test_invalid_template_specs_and_pack_roll_back_without_partial_products(): void
    {
        foreach ([['template_key' => 'unknown'], ['specifications' => ['type' => 'THHN']], ['base_unit' => 'piece'], ['pack_factor' => '0']] as $invalid) {
            $this->post(route('store.products.save', $this->store), array_replace($this->wireInput(), $invalid))->assertSessionHasErrors();
        }
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('product_units', 0);
    }

    public function test_template_edit_preserves_units_and_older_form_preserves_specs(): void
    {
        $this->post(route('store.products.save', $this->store), $this->wireInput())->assertSessionHasNoErrors();
        $product = DB::table('products')->first();
        $this->post(route('store.products.save', $this->store), ['product_id' => $product->id, 'sku' => $product->sku, 'name' => $product->name, 'base_unit' => 'meter', 'fractional' => '1', 'price' => '30'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('products', ['id' => $product->id, 'template_key' => 'wire', 'name' => $product->name, 'price_minor' => 3000]);
        $this->assertDatabaseCount('product_units', 2);
    }

    /** @return array<string, mixed> */
    private function wireInput(): array
    {
        return ['template_key' => 'wire', 'specifications' => ['type' => 'THHN', 'size' => '2.0', 'size_unit' => 'mm²', 'color' => 'Red'], 'base_unit' => 'meter', 'price' => '25', 'pack_label' => 'roll of 100 m', 'pack_factor' => '100'];
    }
}
