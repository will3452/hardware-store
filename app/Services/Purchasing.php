<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Amounts;
use Illuminate\Support\Facades\DB;

final class Purchasing
{
    public function __construct(private StoreAccess $access, private Operations $operations) {}

    /** @param array<string, mixed> $data */
    public function draft(User $actor, array $data, ?string $id = null): PurchaseOrder
    {
        return DB::transaction(function () use ($actor, $data, $id): PurchaseOrder {
            $store = $this->access->lock($actor);
            $this->access->module('purchasing');
            $this->access->module('catalog');
            $this->access->module('inventory');
            $fingerprint = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
            if ($id !== null) {
                $order = PurchaseOrder::whereKey($id)->firstOrFail();
                if ($order->last_edit_key === $data['request_key']) {
                    StoreAccess::check(hash_equals($order->last_edit_fingerprint ?? '', $fingerprint), 'This edit was already submitted with different details. Reload the order.');

                    return $order;
                }
                StoreAccess::check($order->status === 'draft', 'Only draft orders can be edited. This order has already changed status.');
                StoreAccess::check((int) $order->revision === (int) $data['revision'], 'This draft changed in another tab. Reload it before editing.');
                StoreAccess::check(! PurchaseOrder::where('last_edit_key', $data['request_key'])->exists(), 'This edit number belongs to another order. Reload this draft.');
                StoreAccess::check(! PurchaseOrderLine::where('purchase_order_id', $id)->where('received_micro', '>', 0)->exists(), 'Received orders cannot be edited.');
            } elseif ($old = PurchaseOrder::where('request_key', $data['request_key'])->first()) {
                StoreAccess::check(hash_equals($old->fingerprint ?? '', $fingerprint), 'This purchase submission has already been used with different details.');

                return $old;
            } else {
                $order = new PurchaseOrder(['request_key' => $data['request_key'], 'fingerprint' => $fingerprint]);
            }
            $supplier = Supplier::where('active', true)->whereKey($data['supplier_id'])->first();
            StoreAccess::check($supplier !== null, 'Select an active supplier.');
            $order->fill(['supplier_id' => $supplier->id, 'supplier_name' => $supplier->name, 'notes' => $data['notes'] ?? null]);
            if ($id !== null) {
                $order->revision++;
                $order->last_edit_key = $data['request_key'];
                $order->last_edit_fingerprint = $fingerprint;
            }
            $order->save();
            if ($id !== null) {
                PurchaseOrderLine::where('purchase_order_id', $id)->delete();
            }
            $seen = [];
            foreach ($data['lines'] as $input) {
                $product = Product::where('active', true)->whereKey($input['product_id'])->firstOrFail();
                StoreAccess::check(! in_array($product->id, $seen), 'Combine duplicate products into one order line.');
                $seen[] = $product->id;
                $unit = ProductUnit::where('product_id', $product->id)->whereKey($input['unit_id'])->firstOrFail();
                $qty = $this->operations->quantity($product, $input['quantity'], (int) $unit->factor_micro);
                $unitCost = Amounts::parse($input['cost'], 6);
                $cost = Amounts::ratio($unitCost, $qty, (int) $unit->factor_micro);
                PurchaseOrderLine::create(['purchase_order_id' => $order->id, 'product_id' => $product->id, 'name' => $product->name, 'sku' => $product->sku, 'unit_label' => $unit->label, 'factor_micro' => $unit->factor_micro, 'quantity_micro' => $qty, 'unit_cost_micro' => $unitCost, 'total_cost_micro' => $cost]);
            }
            StoreAccess::audit($actor, $store->id, $id ? 'purchase.draft_updated' : 'purchase.drafted', $order->id, ['revision' => (int) $order->revision, 'line_count' => count($seen)]);

            return $order;
        }, 3);
    }
}
