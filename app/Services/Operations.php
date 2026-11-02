<?php

namespace App\Services;

use App\Models\BusinessDocument;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\DocumentLine;
use App\Models\InventoryMovement;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\User;
use App\Support\Amounts;
use App\Support\Tenancy\StoreContext;
use Brick\Math\BigInteger;
use Illuminate\Support\Facades\DB;

final class Operations
{
    public function __construct(private StoreAccess $access) {}

    /** @param array<string, mixed> $data */
    public function post(User $actor, string $kind, array $data): BusinessDocument
    {
        return DB::transaction(function () use ($actor, $kind, $data): BusinessDocument {
            $store = $this->access->lock($actor);
            $fingerprint = hash('sha256', json_encode([$kind, $data], JSON_THROW_ON_ERROR));
            $existing = BusinessDocument::where('request_key', $data['request_key'])->first();
            if ($existing) {
                StoreAccess::check(hash_equals($existing->fingerprint, $fingerprint), 'This submission number has already been used with different details. Open the original document or start a new transaction.');

                return $existing;
            }
            $module = match ($kind) {
                'sale', 'return' => 'sales', 'receipt', 'receipt_reversal' => 'purchasing',
                'expense', 'expense_reversal' => 'expenses', 'adjustment' => 'inventory',
                default => throw new \InvalidArgumentException('Unknown operation.'),
            };
            $resolution = in_array($kind, ['return', 'receipt_reversal', 'expense_reversal']);
            $this->access->module($module, $resolution);
            foreach (StoreAccess::DEPENDENCIES[$module] as $dependency) {
                // Historical corrections use snapshots, without editing the catalog.
                if ($resolution && $dependency === 'catalog') {
                    continue;
                }
                $this->access->module($dependency, $resolution);
            }
            $doc = BusinessDocument::create([
                'kind' => $kind, 'request_key' => $data['request_key'], 'fingerprint' => $fingerprint,
                'user_id' => $actor->id, 'metadata' => ['notes' => $data['notes'] ?? '', 'store_name' => $store->display_name, 'store_address' => $store->address_line_1.', '.$store->city, 'currency' => $store->currency, 'tax_treatment' => 'unconfigured_internal_record'], 'posted_at' => now(),
            ]);
            match ($kind) {
                'sale' => $this->sale($doc, $data),
                'return' => $this->returnSale($doc, $data),
                'adjustment' => $this->adjust($doc, $data),
                'receipt' => $this->receive($doc, $data),
                'receipt_reversal' => $this->reverseReceipt($doc, $data),
                'expense' => $this->expense($doc, $data),
                'expense_reversal' => $this->reverseExpense($doc, $data),
            };
            $doc->save();
            StoreAccess::audit($actor, $store->id, $kind.'.posted', $doc->id, ['total_minor' => $doc->total_minor]);

            return $doc;
        }, 3);
    }

    /** @param array<string, mixed> $data */
    private function sale(BusinessDocument $doc, array $data): void
    {
        $this->matchingSession($data['cash_session_id'] ?? null);
        $seen = [];
        foreach ($data['lines'] as $input) {
            $product = Product::where('active', true)->whereKey($input['product_id'])->firstOrFail();
            StoreAccess::check(! isset($input['catalog_price_minor']) || (int) $input['catalog_price_minor'] === $product->price_minor, 'The catalog price changed for '.$product->name.'. Review its current price before posting.');
            StoreAccess::check(! in_array($product->id, $seen), 'Combine duplicate products into one line.');
            $seen[] = $product->id;
            $unit = ProductUnit::where('product_id', $product->id)->whereKey($input['unit_id'])->firstOrFail();
            $quantity = $this->quantity($product, $input['quantity'], (int) $unit->factor_micro);
            $price = Amounts::parse($input['price']);
            $gross = Amounts::ratio($price, $quantity, (int) $unit->factor_micro);
            $discount = ($input['discount_type'] ?? 'amount') === 'percent'
                ? Amounts::ratio($gross, Amounts::parse($input['discount'] ?? '0'), 10000)
                : Amounts::parse($input['discount'] ?? '0');
            StoreAccess::check($discount <= $gross, 'A line discount cannot exceed its value.');
            $cost = $this->outgoingCost($product, $quantity);
            $line = $this->line($doc, $product, $quantity, $cost, ['unit_label' => $unit->label, 'factor_micro' => $unit->factor_micro, 'price_minor' => $price, 'gross_minor' => $gross, 'discount_minor' => $discount, 'total_minor' => $gross - $discount]);
            $this->move($doc, $product, -$quantity, -$cost, 'Sale');
            $doc->gross_minor += $gross;
            $doc->discount_minor += $discount;
            $doc->total_minor += $line->total_minor;
            $doc->cost_micro += $cost;
        }
        StoreAccess::check($doc->total_minor > 0, 'A paid sale must have a positive total.');
        $noncash = 0;
        $cashTendered = 0;
        $seen = [];
        foreach ($data['payments'] as $payment) {
            $amount = Amounts::parse($payment['amount']);
            if ($amount === 0) {
                continue;
            }
            StoreAccess::check(array_key_exists($payment['method'], app(StoreContext::class)->store()->acceptedPayments()), 'This payment method is no longer accepted at checkout. Review the payment methods before posting.');
            StoreAccess::check(! in_array($payment['method'], $seen), 'Combine payments of the same method.');
            $seen[] = $payment['method'];
            if ($payment['method'] === 'cash') {
                $cashTendered = $amount;
            } else {
                $this->confirmed($payment);
                $noncash += $amount;
                $this->payment($doc, $payment['method'], $amount, $payment['reference']);
            }
        }
        $cashApplied = (int) $doc->total_minor - $noncash;
        StoreAccess::check($cashApplied >= 0 && $cashTendered >= $cashApplied, 'Payments must cover the full total. Noncash payments cannot exceed the total.');
        if ($cashApplied > 0) {
            $this->payment($doc, 'cash', $cashApplied);
            $this->cashMove($cashApplied, 'Sale', $doc->id, $doc->id.':sale');
        }
        $doc->metadata = array_merge($doc->metadata, ['customer' => $data['customer'] ?? '', 'cash_tendered_minor' => $cashTendered, 'change_minor' => $cashTendered - $cashApplied]);
    }

    /** @param array<string, mixed> $data */
    private function returnSale(BusinessDocument $doc, array $data): void
    {
        $this->matchingSession($data['cash_session_id'] ?? null);
        $original = BusinessDocument::where('kind', 'sale')->whereKey($data['original_id'])->firstOrFail();
        $doc->original_id = $original->id;
        $seen = [];
        foreach ($data['lines'] as $input) {
            $quantity = Amounts::parse($input['quantity'], 6);
            if ($quantity === 0) {
                continue;
            }
            $source = DocumentLine::where('business_document_id', $original->id)->whereKey($input['source_line_id'])->firstOrFail();
            StoreAccess::check(! in_array($source->id, $seen), 'Combine duplicate return lines.');
            $seen[] = $source->id;
            $product = Product::whereKey($source->product_id)->firstOrFail();
            $this->whole($product, $quantity);
            $returned = DocumentLine::where('source_line_id', $source->id)->get();
            $previousQuantity = (int) $returned->sum('quantity_micro');
            StoreAccess::check($previousQuantity + $quantity <= $source->quantity_micro, 'The return exceeds the quantity remaining on the original sale.');
            $values = [];
            foreach (['discount_minor', 'total_minor', 'cost_micro'] as $field) {
                $values[$field] = Amounts::ratio((int) $source->$field, $previousQuantity + $quantity, (int) $source->quantity_micro) - (int) $returned->sum($field);
                $doc->$field += $values[$field];
            }
            $values['gross_minor'] = $values['total_minor'] + $values['discount_minor'];
            $doc->gross_minor += $values['gross_minor'];
            $this->line($doc, $product, $quantity, $values['cost_micro'], array_merge($values, [
                'source_line_id' => $source->id, 'name' => $source->name, 'sku' => $source->sku, 'base_unit' => $source->base_unit,
                'unit_label' => $source->unit_label, 'factor_micro' => $source->factor_micro, 'price_minor' => $source->price_minor,
                'disposition' => $input['disposition'],
            ]));
            $this->move($doc, $product, $quantity, $values['cost_micro'], 'Customer return');
            if ($input['disposition'] === 'damaged') {
                $this->move($doc, $product, -$quantity, -$values['cost_micro'], 'Damaged return write-off');
                $doc->loss_micro += $values['cost_micro'];
            }
        }
        StoreAccess::check(count($seen) > 0, 'Enter at least one return quantity.');
        $this->refund($doc, $data);
    }

    /** @param array<string, mixed> $data */
    private function adjust(BusinessDocument $doc, array $data): void
    {
        $product = Product::whereKey($data['product_id'])->firstOrFail();
        StoreAccess::check((string) $product->stock_version === (string) $data['stock_version'], 'Stock changed since this form opened. Refresh, recount, and submit a new adjustment.');
        $quantity = Amounts::parse($data['quantity'], 6);
        $this->whole($product, $quantity);
        if ($data['mode'] === 'opening') {
            StoreAccess::check($product->stock_version === 0, 'Opening stock can only be entered before any movements.');
            $delta = $quantity;
        } elseif ($data['mode'] === 'count') {
            $delta = $quantity - (int) $product->quantity_micro;
        } else {
            $delta = -$quantity;
        }
        StoreAccess::check($delta !== 0, 'There is no stock change to record.');
        $cost = $delta > 0
            ? Amounts::ratio(Amounts::parse($data['unit_cost'], 6), $delta, 1000000)
            : $this->outgoingCost($product, -$delta);
        $this->line($doc, $product, abs($delta), $cost);
        $this->move($doc, $product, $delta, $delta > 0 ? $cost : -$cost, $data['mode'].': '.$data['notes']);
        $doc->total_minor = Amounts::ratio($cost, 1, 10000);
        $doc->loss_micro = $delta < 0 ? $cost : 0;
        $doc->gain_micro = $delta > 0 && $data['mode'] !== 'opening' ? $cost : 0;
        $doc->metadata = array_merge($doc->metadata, ['mode' => $data['mode']]);
    }

    /** @param array<string, mixed> $data */
    private function receive(BusinessDocument $doc, array $data): void
    {
        $order = PurchaseOrder::where('status', 'placed')->whereKey($data['order_id'])->firstOrFail();
        $seen = [];
        foreach ($data['lines'] as $input) {
            if (Amounts::parse($input['quantity'], 6) === 0) {
                continue;
            }
            $source = PurchaseOrderLine::where('purchase_order_id', $order->id)->whereKey($input['order_line_id'])->firstOrFail();
            StoreAccess::check(! in_array($source->id, $seen), 'Duplicate receiving line.');
            $seen[] = $source->id;
            $product = Product::whereKey($source->product_id)->firstOrFail();
            $quantity = $this->quantity($product, $input['quantity'], (int) $source->factor_micro);
            StoreAccess::check($quantity + $source->received_micro <= $source->quantity_micro, 'Received quantity exceeds the open order quantity.');
            $cost = Amounts::ratio((int) $source->total_cost_micro, (int) $source->received_micro + $quantity, (int) $source->quantity_micro)
                - Amounts::ratio((int) $source->total_cost_micro, (int) $source->received_micro, (int) $source->quantity_micro);
            $this->line($doc, $product, $quantity, $cost, ['purchase_order_line_id' => $source->id, 'name' => $source->name, 'sku' => $source->sku, 'unit_label' => $source->unit_label, 'factor_micro' => $source->factor_micro]);
            $this->move($doc, $product, $quantity, $cost, 'Purchase receipt');
            $source->received_micro += $quantity;
            $source->save();
            $doc->cost_micro += $cost;
        }
        StoreAccess::check(count($seen) > 0, 'Enter at least one received quantity.');
        $doc->total_minor = Amounts::ratio((int) $doc->cost_micro, 1, 10000);
        $doc->metadata = array_merge($doc->metadata, ['order_id' => $order->id, 'supplier' => $order->supplier_name]);
        if (! PurchaseOrderLine::where('purchase_order_id', $order->id)->whereColumn('received_micro', '<', 'quantity_micro')->exists()) {
            $order->update(['status' => 'received']);
        }
    }

    /** @param array<string, mixed> $data */
    private function reverseReceipt(BusinessDocument $doc, array $data): void
    {
        $original = BusinessDocument::where('kind', 'receipt')->whereKey($data['original_id'])->firstOrFail();
        StoreAccess::check(! BusinessDocument::where('original_id', $original->id)->exists(), 'This receipt has already been reversed.');
        $order = PurchaseOrder::whereKey($original->metadata['order_id'])->firstOrFail();
        foreach (DocumentLine::where('business_document_id', $original->id)->get() as $source) {
            $product = Product::whereKey($source->product_id)->firstOrFail();
            $movement = InventoryMovement::where('business_document_id', $original->id)->where('product_id', $product->id)->firstOrFail();
            StoreAccess::check((int) $product->stock_version === (int) $movement->stock_version, 'This receipt cannot be reversed because a product has later stock movements.');
            $this->line($doc, $product, (int) $source->quantity_micro, (int) $source->cost_micro);
            $this->move($doc, $product, -(int) $source->quantity_micro, -(int) $source->cost_micro, 'Receipt reversal');
            $poLine = PurchaseOrderLine::whereKey($source->purchase_order_line_id)->firstOrFail();
            $poLine->received_micro -= $source->quantity_micro;
            $poLine->save();
        }
        if ($order->status === 'received') {
            $order->update(['status' => 'placed']);
        }
        $doc->original_id = $original->id;
        $doc->total_minor = $original->total_minor;
        $doc->cost_micro = $original->cost_micro;
    }

    /** @param array<string, mixed> $data */
    private function expense(BusinessDocument $doc, array $data): void
    {
        $doc->total_minor = Amounts::parse($data['amount']);
        StoreAccess::check($doc->total_minor > 0, 'Expense amount must be positive.');
        $doc->metadata = array_merge($doc->metadata, ['category' => $data['category'], 'receipt_date' => $data['receipt_date'], 'payee' => $data['payee'] ?? '']);
        if ($data['method'] === 'cash') {
            $this->matchingSession($data['cash_session_id'] ?? null);
            $this->cashMove(-(int) $doc->total_minor, 'Paid expense', $doc->id, $doc->id.':expense');
        } else {
            $this->confirmed($data);
        }
        $this->payment($doc, $data['method'], (int) $doc->total_minor, $data['reference'] ?? null);
    }

    /** @param array<string, mixed> $data */
    private function reverseExpense(BusinessDocument $doc, array $data): void
    {
        $original = BusinessDocument::where('kind', 'expense')->whereKey($data['original_id'])->firstOrFail();
        StoreAccess::check(! BusinessDocument::where('original_id', $original->id)->exists(), 'This expense has already been reversed.');
        $payment = Payment::where('business_document_id', $original->id)->firstOrFail();
        $doc->original_id = $original->id;
        $doc->total_minor = $original->total_minor;
        $doc->metadata = array_merge($doc->metadata, ['category' => $original->metadata['category']]);
        if ($payment->method === 'cash') {
            $this->matchingSession($data['cash_session_id'] ?? null);
            $this->cashMove((int) $doc->total_minor, 'Expense reversal', $doc->id, $doc->id.':expense-reversal');
        } else {
            $this->confirmed($data);
        }
        $this->payment($doc, $payment->method, (int) $doc->total_minor, $data['reference'] ?? null);
    }

    /** @param array<string, mixed> $data */
    private function refund(BusinessDocument $doc, array $data): void
    {
        if ($data['method'] === 'cash') {
            $this->cashMove(-(int) $doc->total_minor, 'Customer refund', $doc->id, $doc->id.':refund');
        } else {
            $this->confirmed($data);
        }
        $this->payment($doc, $data['method'], (int) $doc->total_minor, $data['reference'] ?? null);
    }

    /** @param array<string, mixed> $data */
    private function confirmed(array $data): void
    {
        StoreAccess::check(! empty($data['confirmed']) && ! empty(trim($data['reference'] ?? '')), 'Confirm the completed noncash payment/refund and provide its reference.');
    }

    private function payment(BusinessDocument $doc, string $method, int $amount, ?string $reference = null): void
    {
        StoreAccess::check(in_array($method, ['cash', 'card', 'bank', 'ewallet']), 'Unknown payment method.');
        Payment::create(['business_document_id' => $doc->id, 'method' => $method, 'amount_minor' => $amount, 'reference' => $reference]);
    }

    private function matchingSession(?string $id): void
    {
        StoreAccess::check($this->session()->id === $id, 'The cash session has changed. Review the current drawer before posting.');
    }

    public function session(): CashSession
    {
        $session = CashSession::whereNull('closed_at')->first();
        StoreAccess::check($session !== null, 'Open a cash session before selling, refunding, or recording a cash payment.');

        return $session;
    }

    public function cashMove(int $amount, string $reason, ?string $documentId, string $requestKey): void
    {
        $session = $this->session();
        StoreAccess::check($session->expected_minor + $amount >= 0, 'There is not enough expected cash in the drawer. Record a legitimate cash addition or use a confirmed noncash method.');
        CashMovement::create(['cash_session_id' => $session->id, 'business_document_id' => $documentId, 'request_key' => $requestKey, 'amount_minor' => $amount, 'reason' => $reason]);
        $session->expected_minor += $amount;
        $session->save();
    }

    public function quantity(Product $product, mixed $input, int $factor): int
    {
        $entered = Amounts::parse($input, 6);
        $numerator = BigInteger::of($entered)->multipliedBy($factor);
        StoreAccess::check($numerator->remainder(1000000)->isZero(), 'The converted base quantity exceeds six decimal places.');
        $quantity = $numerator->dividedBy(1000000)->toInt();
        StoreAccess::check($quantity > 0 && $quantity <= 999999999000000, 'Quantity must be positive and within the supported range.');
        $this->whole($product, $quantity);

        return $quantity;
    }

    private function whole(Product $product, int $quantity): void
    {
        StoreAccess::check($product->fractional || $quantity % 1000000 === 0, $product->name.' must use whole '.$product->base_unit.' quantities.');
    }

    private function outgoingCost(Product $product, int $quantity): int
    {
        StoreAccess::check($quantity > 0 && $quantity <= $product->quantity_micro, 'Insufficient stock for '.$product->name.'.');

        return Amounts::ratio((int) $product->value_micro, $quantity, (int) $product->quantity_micro);
    }

    /** @param array<string, mixed> $extra */
    private function line(BusinessDocument $doc, Product $product, int $quantity, int $cost, array $extra = []): DocumentLine
    {
        return DocumentLine::create(array_merge([
            'business_document_id' => $doc->id, 'product_id' => $product->id, 'name' => $product->name, 'sku' => $product->sku,
            'base_unit' => $product->base_unit, 'unit_label' => $product->base_unit, 'factor_micro' => 1000000,
            'quantity_micro' => $quantity, 'cost_micro' => $cost,
        ], $extra));
    }

    private function move(BusinessDocument $doc, Product $product, int $quantity, int $value, string $reason): void
    {
        StoreAccess::check($product->quantity_micro + $quantity >= 0 && $product->value_micro + $value >= 0, 'This movement would make inventory negative.');
        $product->quantity_micro += $quantity;
        $product->value_micro += $value;
        $product->stock_version++;
        StoreAccess::check($product->quantity_micro !== 0 || $product->value_micro === 0, 'Zero stock must carry zero inventory value.');
        $product->save();
        InventoryMovement::create(['product_id' => $product->id, 'business_document_id' => $doc->id, 'quantity_micro' => $quantity, 'value_micro' => $value, 'stock_version' => $product->stock_version, 'reason' => mb_substr($reason, 0, 100)]);
    }
}
