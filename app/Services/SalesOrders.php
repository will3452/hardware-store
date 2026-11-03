<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\DocumentLine;
use App\Models\OrderRelease;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\SalesOrder;
use App\Models\SalesOrderAction;
use App\Models\SalesOrderLine;
use App\Models\User;
use App\Support\Amounts;
use App\Support\Tenancy\StoreContext;
use Illuminate\Support\Facades\DB;

final class SalesOrders
{
    /** @param array<string, mixed> $data */
    public function act(User $actor, string $action, ?string $id, array $data): SalesOrder
    {
        return DB::transaction(function () use ($actor, $action, $id, $data): SalesOrder {
            $access = app(StoreAccess::class);
            $store = $access->lock($actor);
            $fingerprint = hash('sha256', json_encode([$action, $id, $data], JSON_THROW_ON_ERROR));
            $previous = SalesOrderAction::where('request_key', $data['request_key'])->first();
            if ($previous) {
                StoreAccess::check(hash_equals($previous->fingerprint, $fingerprint), 'This submission was already used with different details.');

                return SalesOrder::whereKey($previous->sales_order_id)->firstOrFail();
            }
            $access->module('orders', in_array($action, ['close', 'receive']));
            $order = $id ? SalesOrder::whereKey($id)->firstOrFail() : new SalesOrder;
            if ($order->exists) {
                StoreAccess::check($order->revision === (int) $data['revision'], 'This order changed. Refresh before continuing.');
            }
            if (in_array($action, ['save', 'issue', 'accept', 'revise', 'release'])) {
                $access->module('customers');
                $access->module('catalog');
            }
            match ($action) {
                'save' => $this->save($order, $data),
                'issue' => $this->issue($order),
                'accept' => $this->accept($order, $data),
                'close' => $this->close($order, $data['notes']),
                'release' => $this->release($actor, $order, $data),
                'receive' => $this->receive($order, $data),
                'revise' => $order = $this->revise($order, $data['notes']),
                default => throw new \InvalidArgumentException('Unknown order action.'),
            };
            $order->revision++;
            $order->save();
            SalesOrderAction::create(['request_key' => $data['request_key'], 'fingerprint' => $fingerprint, 'sales_order_id' => $order->id, 'action' => $action, 'created_at' => now()]);
            StoreAccess::audit($actor, $store->id, 'order.'.$action, $order->id, ['revision' => $order->revision]);

            return $order;
        }, 3);
    }

    /** @param array<string, mixed> $data */
    private function save(SalesOrder $order, array $data): void
    {
        StoreAccess::check(! $order->exists || $order->status === 'draft', 'Only a draft can be edited. Create a new revision of an issued quotation.');
        $customer = Customer::where('active', true)->whereKey($data['customer_id'])->firstOrFail();
        $store = app(StoreContext::class)->store();
        $order->fill(['store_snapshot' => ['name' => $store->display_name, 'address' => $store->address_line_1.', '.$store->city, 'currency' => $store->currency], 'customer_id' => $customer->id, 'customer_name' => $customer->name, 'customer_address' => $customer->address, 'valid_until' => $data['valid_until'], 'project' => $data['project'] ?? null, 'notes' => $data['notes'] ?? null, 'total_minor' => 0, 'status' => 'draft']);
        $order->save();
        SalesOrderLine::where('sales_order_id', $order->id)->delete();
        $seen = [];
        foreach ($data['lines'] as $input) {
            $product = Product::where('active', true)->whereKey($input['product_id'])->firstOrFail();
            StoreAccess::check(! isset($seen[$product->id]), 'Combine repeated products into one quotation line.');
            $seen[$product->id] = true;
            $unit = ProductUnit::where('product_id', $product->id)->whereKey($input['unit_id'])->firstOrFail();
            $quantity = app(Operations::class)->quantity($product, $input['quantity'], $unit->factor_micro);
            $price = Amounts::parse($input['price']);
            $gross = Amounts::ratio($price, $quantity, $unit->factor_micro);
            $discount = $input['discount_type'] === 'percent' ? Amounts::ratio($gross, Amounts::parse($input['discount']), 10000) : Amounts::parse($input['discount']);
            StoreAccess::check($gross > $discount, 'Every quotation line must have a positive amount after discount.');
            SalesOrderLine::create(['sales_order_id' => $order->id, 'product_id' => $product->id, 'product_unit_id' => $unit->id, 'name' => $product->name, 'sku' => $product->sku, 'base_unit' => $product->base_unit, 'unit_label' => $unit->label, 'factor_micro' => $unit->factor_micro, 'quantity_micro' => $quantity, 'price_minor' => $price, 'gross_minor' => $gross, 'discount_minor' => $discount, 'total_minor' => $gross - $discount]);
            $order->total_minor += $gross - $discount;
        }
        StoreAccess::check($order->total_minor > 0, 'Add at least one priced item.');
    }

    private function issue(SalesOrder $order): void
    {
        StoreAccess::check($order->status === 'draft' && $order->total_minor > 0, 'Only a priced draft can be issued.');
        StoreAccess::check($order->valid_until >= now()->timezone(app(StoreContext::class)->store()->timezone)->toDateString(), 'Choose a current validity date before issuing.');
        $order->status = 'issued';
        $order->issued_at = now();
    }

    /** @param array<string, mixed> $data */
    private function accept(SalesOrder $order, array $data): void
    {
        StoreAccess::check($order->status === 'issued', 'Only an issued quotation can be accepted.');
        StoreAccess::check($order->valid_until >= now()->timezone(app(StoreContext::class)->store()->timezone)->toDateString(), 'This quotation expired. Create a revised quotation for acceptance.');
        Customer::where('active', true)->whereKey($order->customer_id)->firstOrFail();
        $order->status = 'accepted';
        $order->accepted_at = now();
        $order->acceptance_note = $data['notes'];
    }

    private function close(SalesOrder $order, string $reason): void
    {
        StoreAccess::check(in_array($order->status, ['draft', 'issued', 'accepted', 'partial']), 'This order has no open remainder.');
        foreach (SalesOrderLine::where('sales_order_id', $order->id)->get() as $line) {
            $line->cancelled_micro = $line->quantity_micro - $line->released_micro;
            $line->save();
        }
        $order->status = $order->status === 'partial' ? 'closed' : 'cancelled';
        $order->close_reason = $reason;
    }

    private function revise(SalesOrder $order, string $reason): SalesOrder
    {
        StoreAccess::check(in_array($order->status, ['issued', 'accepted', 'partial']), 'Revise an issued quotation or the open remainder of an accepted order.');
        $new = new SalesOrder;
        $new->fill($order->only(['store_snapshot', 'customer_id', 'customer_name', 'customer_address', 'valid_until', 'project', 'notes']));
        $new->replaces_id = $order->id;
        $new->quote_version = $order->quote_version + 1;
        $new->status = 'draft';
        $new->total_minor = 0;
        $new->save();
        foreach (SalesOrderLine::where('sales_order_id', $order->id)->get() as $line) {
            $remaining = $line->quantity_micro - $line->released_micro - $line->cancelled_micro;
            if ($remaining <= 0) {
                continue;
            }
            $copy = new SalesOrderLine;
            $copy->fill($line->only(['product_id', 'product_unit_id', 'name', 'sku', 'base_unit', 'unit_label', 'factor_micro', 'price_minor']));
            $copy->sales_order_id = $new->id;
            $copy->quantity_micro = $remaining;
            $copy->total_minor = $line->total_minor - $line->released_total_minor;
            $copy->discount_minor = $line->discount_minor - $line->released_discount_minor;
            $copy->gross_minor = $copy->total_minor + $copy->discount_minor;
            $copy->save();
            $new->total_minor += $copy->total_minor;
        }
        StoreAccess::check($new->total_minor > 0, 'There is no unfulfilled remainder to revise.');
        $this->close($order, $reason);
        $order->revision++;
        $order->save();

        return $new;
    }

    /** @param array<string, mixed> $data */
    private function release(User $actor, SalesOrder $order, array $data): void
    {
        StoreAccess::check(in_array($order->status, ['accepted', 'partial']), 'Accept the quotation before releasing goods.');
        $snapshots = [];
        $saleLines = [];
        $seen = [];
        foreach ($data['lines'] as $input) {
            $quantity = Amounts::parse($input['quantity'], 6);
            if ($quantity === 0) {
                continue;
            }
            $line = SalesOrderLine::where('sales_order_id', $order->id)->whereKey($input['order_line_id'])->firstOrFail();
            StoreAccess::check(! isset($seen[$line->id]) && $quantity <= $line->quantity_micro - $line->released_micro - $line->cancelled_micro, 'The release repeats a line or exceeds its remaining quantity.');
            $seen[$line->id] = true;
            $total = Amounts::ratio($line->total_minor, $line->released_micro + $quantity, $line->quantity_micro) - $line->released_total_minor;
            $discount = Amounts::ratio($line->discount_minor, $line->released_micro + $quantity, $line->quantity_micro) - $line->released_discount_minor;
            $snapshots[$line->product_id] = $line->only(['name', 'sku', 'base_unit', 'unit_label', 'factor_micro', 'price_minor']) + ['quantity_micro' => $quantity, 'gross_minor' => $total + $discount, 'discount_minor' => $discount, 'total_minor' => $total];
            $saleLines[] = ['product_id' => $line->product_id, 'unit_id' => $line->product_unit_id, 'quantity' => Amounts::display($quantity, 6), 'price' => Amounts::display($line->price_minor), 'discount' => '0', 'discount_type' => 'amount'];
            $line->released_micro += $quantity;
            $line->released_total_minor += $total;
            $line->released_discount_minor += $discount;
            $line->save();
        }
        StoreAccess::check(count($saleLines) > 0, 'Enter at least one release quantity.');
        $saleData = ['request_key' => $data['request_key'], 'cash_session_id' => $data['cash_session_id'], 'lines' => $saleLines, 'payments' => $data['payments'], 'settlement' => $data['settlement'], 'customer_id' => $order->customer_id, 'due_date' => $data['due_date'] ?? null, 'notes' => 'Release from order '.$order->id.'. '.($data['notes'] ?? '')];
        $sale = app(Operations::class)->post($actor, 'sale', $saleData, $snapshots);
        OrderRelease::create(['sales_order_id' => $order->id, 'business_document_id' => $sale->id, 'method' => $data['method'], 'recipient' => $data['recipient'], 'address' => $data['address'] ?? null, 'reference' => $data['reference'] ?? null, 'released_at' => now(), 'received_at' => $data['method'] === 'pickup' ? now() : null, 'received_by' => $data['method'] === 'pickup' ? $data['recipient'] : null]);
        $open = SalesOrderLine::where('sales_order_id', $order->id)->whereRaw('quantity_micro > released_micro + cancelled_micro')->exists();
        $order->status = $open ? 'partial' : 'completed';
    }

    /** @param array<string, mixed> $data */
    private function receive(SalesOrder $order, array $data): void
    {
        $release = OrderRelease::where('sales_order_id', $order->id)->whereKey($data['release_id'])->firstOrFail();
        StoreAccess::check($release->method === 'delivery' && $release->received_at === null, 'This release is already received or is a pickup.');
        $sold = DocumentLine::where('business_document_id', $release->business_document_id);
        $returned = DocumentLine::whereIn('source_line_id', (clone $sold)->select('id'))->sum('quantity_micro');
        StoreAccess::check($returned < (clone $sold)->sum('quantity_micro'), 'All goods on this dispatch were returned. Do not mark it received.');
        $release->received_at = now();
        $release->received_by = $data['recipient'];
        $release->save();
    }
}
