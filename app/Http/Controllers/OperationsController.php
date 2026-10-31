<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\AuditEvent;
use App\Models\BusinessDocument;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\DocumentAttachment;
use App\Models\DocumentLine;
use App\Models\InventoryMovement;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\StoreModule;
use App\Models\Supplier;
use App\Services\Operations;
use App\Services\Reports;
use App\Services\StoreAccess;
use App\Support\Amounts;
use App\Support\Tenancy\StoreContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OperationsController extends Controller
{
    public function __construct(private StoreContext $context, private StoreAccess $access, private Operations $operations) {}

    public function page(Request $request, string $store, string $page = 'overview'): View|StreamedResponse
    {
        abort_unless(in_array($page, ['overview', 'products', 'inventory', 'purchasing', 'sales', 'cash', 'expenses', 'reports', 'audit', 'profile']), 404);
        $this->access->history($page);
        $data = ['store' => $this->context->store(), 'page' => $page, 'modules' => StoreModule::pluck('mode', 'module_key')->all()];
        $data['session'] = CashSession::whereNull('closed_at')->first();
        if (in_array($page, ['products', 'inventory', 'sales', 'purchasing'])) {
            $request->validate(['q' => ['nullable', 'string', 'max:100']]);
            $query = Product::query()->orderBy('name');
            if ($request->filled('q')) {
                $q = $request->string('q')->toString();
                $query->where(fn ($builder) => $builder->where('name', 'like', '%'.$q.'%')->orWhere('sku', 'like', '%'.$q.'%')->orWhere('barcode', $q));
            }
            $data['products'] = $query->paginate(30)->withQueryString();
            $data['units'] = ProductUnit::whereIn('product_id', $data['products']->pluck('id'))->get()->groupBy('product_id');
        }
        if ($page === 'overview') {
            $data['announcements'] = Announcement::whereNotNull('publish_at')->where('publish_at', '<=', now())->whereNull('withdrawn_at')
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->where(fn ($q) => $q->whereNull('store_ids')->orWhereJsonContains('store_ids', $store))->latest('publish_at')->limit(20)->get();
            $data['lowStock'] = Product::where('active', true)->whereColumn('quantity_micro', '<=', 'reorder_micro')->orderBy('name')->limit(10)->get();
            $data['productCount'] = Product::count();
        }
        if (in_array($page, ['overview', 'reports']) && ($data['modules']['reports'] ?? null) === 'enabled') {
            $request->validate(['from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from']]);
            $data['from'] = $request->input('from', now()->timezone($data['store']->timezone)->format('Y-m-d'));
            $data['to'] = $request->input('to', $data['from']);
            StoreAccess::check($data['to'] >= $data['from'], 'The end date must follow the start date.');
            $data['metrics'] = app(Reports::class)->summarize([$store], $data['from'], $data['to'], $data['store']->timezone);
            if ($page === 'reports') {
                $data['performance'] = app(Reports::class)->products($store, $data['from'], $data['to'], $data['store']->timezone)->paginate(25, ['*'], 'performance_page')->withQueryString();
                $data['stockReport'] = app(Reports::class)->stock($store, $data['to'], $data['store']->timezone)->paginate(25, ['*'], 'stock_page')->withQueryString();
            }
            if ($page === 'reports' && in_array($request->input('export'), ['products', 'inventory'], true)) {
                $type = $request->input('export');
                $query = $type === 'products' ? app(Reports::class)->products($store, $data['from'], $data['to'], $data['store']->timezone) : app(Reports::class)->stock($store, $data['to'], $data['store']->timezone);

                return response()->streamDownload(function () use ($data, $type, $query): void {
                    $out = fopen('php://output', 'w');
                    if ($out === false) {
                        throw new \RuntimeException('Cannot open report output.');
                    }
                    fputcsv($out, ['Store', Reports::csvCell($data['store']->display_name), 'Currency', $data['store']->currency, 'Timezone', $data['store']->timezone, 'From', $data['from'], 'To', $data['to']], escape: '');
                    fputcsv($out, $type === 'products' ? ['SKU', 'Product', 'Base unit', 'Net quantity', 'Recorded sales', 'Cost of goods', 'Gross profit estimate'] : ['SKU', 'Product', 'Base unit', 'On hand at end date', 'Inventory value'], escape: '');
                    foreach ($query->cursor() as $row) {
                        $cells = [Reports::csvCell($row->sku), Reports::csvCell($row->name), Reports::csvCell($row->base_unit), Amounts::display($row->quantity, 6)];
                        if ($type === 'products') {
                            $cost = Amounts::ratio((int) $row->cost, 1, 10000);
                            $cells = array_merge($cells, [Amounts::display($row->sales), Amounts::display($cost), Amounts::display((int) $row->sales - $cost)]);
                        } else {
                            $cells[] = Amounts::display(Amounts::ratio((int) $row->value, 1, 10000));
                        }
                        fputcsv($out, $cells, escape: '');
                    }
                    fclose($out);
                }, $type.'-report.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
            }
            if ($page === 'reports' && $request->boolean('export')) {
                return response()->streamDownload(function () use ($data): void {
                    $out = fopen('php://output', 'w');
                    if ($out === false) {
                        throw new \RuntimeException('Cannot open report output.');
                    }
                    fputcsv($out, ['Store', 'Currency', 'Timezone', 'From', 'To', 'Metric', 'Value'], escape: '');
                    foreach ($data['metrics'] as $metric => $amount) {
                        fputcsv($out, [Reports::csvCell($data['store']->display_name), $data['store']->currency, $data['store']->timezone, $data['from'], $data['to'], $metric, $metric === 'sale_count' ? $amount : Amounts::display($amount)], escape: '');
                    }
                    fclose($out);
                }, 'store-report.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
            }
        }
        if ($page === 'inventory') {
            $data['movements'] = InventoryMovement::latest('created_at')->paginate(25, ['*'], 'movement_page');
        }
        if ($page === 'purchasing') {
            $data['suppliers'] = Supplier::orderBy('name')->get();
            $data['orders'] = PurchaseOrder::latest()->paginate(20, ['*'], 'orders_page');
        }
        if (in_array($page, ['sales', 'expenses'])) {
            $data['documents'] = BusinessDocument::whereIn('kind', $page === 'sales' ? ['sale', 'return'] : ['expense', 'expense_reversal'])->latest('posted_at')->paginate(20, ['*'], 'history_page');
        }
        if ($page === 'cash') {
            $data['sessions'] = CashSession::latest('opened_at')->paginate(20);
            $data['movements'] = CashMovement::where('cash_session_id', $data['session']?->id)->oldest('created_at')->get();
        }
        if ($page === 'audit') {
            $data['events'] = AuditEvent::where('store_id', $store)->latest('created_at')->paginate(40);
        }

        if (in_array($page, ['sales', 'purchasing'])) {
            $data['oldCart'] = [];
            foreach (session()->getOldInput('lines', []) as $input) {
                $product = Product::whereKey($input['product_id'] ?? '')->first();
                if ($product) {
                    $data['oldCart'][] = $input + ['name' => $product->name, 'sku' => $product->sku, 'base_unit' => $product->base_unit, 'units' => ProductUnit::where('product_id', $product->id)->get()->toArray(), 'discount' => '0', 'discount_type' => 'amount'];
                }
            }
        }

        return view('operations.'.$page, $data);
    }

    public function catalog(Request $request): JsonResponse
    {
        $this->access->module('catalog');
        $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $q = $request->string('q')->toString();
        $products = Product::where('active', true)->where(fn ($builder) => $builder->where('name', 'like', '%'.$q.'%')->orWhere('sku', 'like', '%'.$q.'%')->orWhere('barcode', $q))->orderBy('name')->limit(30)->get();
        $units = ProductUnit::whereIn('product_id', $products->pluck('id'))->get()->groupBy('product_id');

        return response()->json($products->map(fn ($product) => ['id' => $product->id, 'name' => $product->name, 'sku' => $product->sku, 'barcode' => $product->barcode, 'base_unit' => $product->base_unit, 'quantity_micro' => $product->quantity_micro, 'price_minor' => $product->price_minor, 'units' => $units->get($product->id, collect())->values()])->values());
    }

    public function product(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['nullable', 'ulid'], 'sku' => ['required', 'string', 'max:80'], 'barcode' => ['nullable', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:255'], 'category' => ['nullable', 'string', 'max:80'], 'brand' => ['nullable', 'string', 'max:80'],
            'base_unit' => ['required', 'string', 'max:30'], 'fractional' => ['boolean'], 'active' => ['boolean'],
            'price' => ['required', 'string', 'max:20'], 'reorder' => ['required', 'string', 'max:20'],
        ]);
        DB::transaction(function () use ($data, $request): void {
            $store = $this->access->lock($request->user());
            $this->access->module('catalog');
            $product = ! empty($data['product_id']) ? Product::whereKey($data['product_id'])->firstOrFail() : new Product;
            StoreAccess::check(! Product::where('sku', $data['sku'])->when($product->exists, fn ($q) => $q->where('id', '!=', $product->id))->exists(), 'That SKU is already in use.');
            StoreAccess::check(empty($data['barcode']) || ! Product::where('barcode', $data['barcode'])->when($product->exists, fn ($q) => $q->where('id', '!=', $product->id))->exists(), 'That barcode is already in use.');
            if ($product->exists) {
                StoreAccess::check($product->base_unit === $data['base_unit'] && (bool) $product->fractional === (bool) ($data['fractional'] ?? false), 'Base units and fractional settings are fixed. Create a new SKU for a different base unit.');
            }
            $product->fill(array_intersect_key($data, array_flip(['sku', 'barcode', 'name', 'category', 'brand', 'base_unit'])));
            $product->price_minor = Amounts::parse($data['price']);
            $product->reorder_micro = Amounts::parse($data['reorder'], 6);
            $product->fractional = $data['fractional'] ?? false;
            $product->active = $data['active'] ?? true;
            $product->save();
            ProductUnit::firstOrCreate(['product_id' => $product->id, 'label' => $product->base_unit], ['factor_micro' => 1000000]);
            StoreAccess::audit($request->user(), $store->id, 'product.saved', $product->id, ['sku' => $product->sku, 'active' => $product->active]);
        }, 3);

        return back()->with('status', 'Product saved.');
    }

    public function unit(Request $request): RedirectResponse
    {
        $data = $request->validate(['product_id' => ['required', 'ulid'], 'label' => ['required', 'string', 'max:30'], 'factor' => ['required', 'string', 'max:20']]);
        DB::transaction(function () use ($data, $request): void {
            $store = $this->access->lock($request->user());
            $this->access->module('catalog');
            $product = Product::whereKey($data['product_id'])->firstOrFail();
            $factor = Amounts::parse($data['factor'], 6);
            StoreAccess::check($factor > 0 && ($product->fractional || $factor % 1000000 === 0), 'Enter a positive conversion compatible with the base unit.');
            StoreAccess::check(! ProductUnit::where('product_id', $product->id)->where('label', $data['label'])->exists(), 'That unit already exists. Use a new label for a new conversion.');
            $unit = ProductUnit::create(['product_id' => $product->id, 'label' => $data['label'], 'factor_micro' => $factor]);
            StoreAccess::audit($request->user(), $store->id, 'product.unit_added', $unit->id);
        });

        return back()->with('status', 'Unit added. Its conversion is fixed for historical accuracy.');
    }

    public function post(Request $request, string $store, string $kind): RedirectResponse
    {
        abort_unless(in_array($kind, ['sale', 'return', 'adjustment', 'receipt', 'receipt_reversal', 'expense', 'expense_reversal']), 404);
        $rules = ['cash_session_id' => [in_array($kind, ['sale', 'return']) || ($kind === 'expense' && $request->input('method') === 'cash') ? 'required' : 'nullable', 'ulid'], 'request_key' => ['required', 'uuid'], 'notes' => ['nullable', 'string', 'max:2000']];
        $payment = ['method' => ['required', Rule::in(['cash', 'card', 'bank', 'ewallet'])], 'reference' => ['nullable', 'string', 'max:255'], 'confirmed' => ['nullable', 'boolean']];
        $rules = array_merge($rules, match ($kind) {
            'sale' => [
                'customer' => ['nullable', 'string', 'max:255'], 'lines' => ['required', 'array', 'min:1', 'max:100'],
                'lines.*.product_id' => ['required', 'ulid'], 'lines.*.unit_id' => ['required', 'ulid'],
                'lines.*.quantity' => ['required', 'string', 'max:20'], 'lines.*.price' => ['required', 'string', 'max:20'],
                'lines.*.discount' => ['required', 'string', 'max:20'], 'lines.*.discount_type' => ['required', Rule::in(['amount', 'percent'])],
                'payments' => ['required', 'array', 'min:1', 'max:4'], 'payments.*.method' => $payment['method'],
                'payments.*.amount' => ['required', 'string', 'max:20'], 'payments.*.reference' => $payment['reference'], 'payments.*.confirmed' => $payment['confirmed'],
            ],
            'return' => array_merge($payment, ['original_id' => ['required', 'ulid'], 'lines' => ['required', 'array', 'min:1', 'max:100'], 'lines.*.source_line_id' => ['required', 'ulid'], 'lines.*.quantity' => ['required', 'string', 'max:20'], 'lines.*.disposition' => ['required', Rule::in(['sellable', 'damaged'])]]),
            'receipt' => ['order_id' => ['required', 'ulid'], 'lines' => ['required', 'array', 'min:1', 'max:100'], 'lines.*.order_line_id' => ['required', 'ulid'], 'lines.*.quantity' => ['required', 'string', 'max:20']],
            'adjustment' => ['product_id' => ['required', 'ulid'], 'stock_version' => ['required', 'integer', 'min:0'], 'mode' => ['required', Rule::in(['opening', 'count', 'damage'])], 'quantity' => ['required', 'string', 'max:20'], 'unit_cost' => ['nullable', 'string', 'max:20'], 'notes' => ['required', 'string', 'max:2000']],
            'expense' => array_merge($payment, ['category' => ['required', Rule::in(['rent', 'utilities', 'wages', 'transport', 'supplies', 'repairs', 'other'])], 'payee' => ['nullable', 'string', 'max:255'], 'amount' => ['required', 'string', 'max:20'], 'receipt_date' => ['required', 'date_format:Y-m-d'], 'notes' => ['required', 'string', 'max:2000']]),
            'receipt_reversal' => ['original_id' => ['required', 'ulid'], 'notes' => ['required', 'string', 'max:2000']],
            'expense_reversal' => ['original_id' => ['required', 'ulid'], 'reference' => $payment['reference'], 'confirmed' => $payment['confirmed'], 'notes' => ['required', 'string', 'max:2000']],
        });
        $data = $request->validate($rules);
        $document = $this->operations->post($request->user(), $kind, $data);

        return redirect()->route('store.document', [$store, $document->id])->with('status', 'Transaction posted successfully.');
    }

    public function document(string $store, string $document): View
    {
        $doc = BusinessDocument::whereKey($document)->firstOrFail();

        return view('operations.document', ['store' => $this->context->store(), 'page' => 'history', 'doc' => $doc,
            'session' => CashSession::whereNull('closed_at')->first(), 'modules' => StoreModule::pluck('mode', 'module_key')->all(), 'lines' => DocumentLine::where('business_document_id', $doc->id)->get(),
            'payments' => Payment::where('business_document_id', $doc->id)->get(),
            'returns' => DocumentLine::whereIn('source_line_id', DocumentLine::where('business_document_id', $doc->id)->select('id'))->get()->groupBy('source_line_id'),
            'corrections' => BusinessDocument::where('original_id', $doc->id)->get(),
            'attachments' => DocumentAttachment::where('business_document_id', $doc->id)->get(),
        ]);
    }

    public function cash(Request $request, string $store, string $action): RedirectResponse
    {
        abort_unless(in_array($action, ['open', 'move', 'close']), 404);
        $data = $request->validate(['request_key' => ['required', 'uuid'], 'session_id' => [$action === 'open' ? 'nullable' : 'required', 'ulid'], 'amount' => ['required', 'string', 'max:20'], 'direction' => ['nullable', Rule::in(['in', 'out'])], 'notes' => [$action === 'move' ? 'required' : 'nullable', 'string', 'max:255']]);
        DB::transaction(function () use ($data, $action, $request): void {
            $store = $this->access->lock($request->user());
            $this->access->module('cash', true);
            $amount = Amounts::parse($data['amount']);
            if ($action === 'open') {
                if ($old = CashSession::where('request_key', $data['request_key'])->first()) {
                    StoreAccess::check((int) $old->opening_minor === $amount, 'This submission was already used with different details.');

                    return;
                }
                StoreAccess::check(! CashSession::whereNull('closed_at')->exists(), 'A cash session is already open.');
                $session = CashSession::create(['user_id' => $request->user()->id, 'request_key' => $data['request_key'], 'opening_minor' => $amount, 'expected_minor' => $amount, 'opened_at' => now()]);
            } else {
                $session = CashSession::whereKey($data['session_id'])->firstOrFail();
                if ($action === 'close' && $session->closed_at) {
                    StoreAccess::check((int) $session->counted_minor === $amount, 'This session was already closed with a different count.');

                    return;
                }
                if ($action === 'move' && $old = CashMovement::where('request_key', $data['request_key'])->first()) {
                    StoreAccess::check($old->cash_session_id === $session->id && (int) $old->amount_minor === (($data['direction'] ?? 'in') === 'out' ? -$amount : $amount) && $old->reason === $data['notes'], 'This submission was already used with different details.');

                    return;
                }
                StoreAccess::check(! $session->closed_at, 'This session is already closed.');
                if ($action === 'close') {
                    $variance = $amount - (int) $session->expected_minor;
                    StoreAccess::check($variance === 0 || ! empty($data['notes']), 'Explain the cash variance before closing.');
                    $session->update(['counted_minor' => $amount, 'variance_minor' => $variance, 'closed_at' => now(), 'notes' => $data['notes'] ?? null]);
                } else {
                    StoreAccess::check($amount > 0, 'Cash movements must be positive.');
                    if (($data['direction'] ?? 'in') === 'out') {
                        $this->access->module('cash');
                    }
                    $this->operations->cashMove(($data['direction'] ?? 'in') === 'out' ? -$amount : $amount, $data['notes'], null, $data['request_key']);
                }
            }
            StoreAccess::audit($request->user(), $store->id, 'cash.'.$action, $session->id, ['amount_minor' => $amount]);
        }, 3);

        return back()->with('status', 'Cash session updated.');
    }

    public function supplier(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'contact' => ['nullable', 'string', 'max:255'], 'address' => ['nullable', 'string', 'max:2000']]);
        DB::transaction(function () use ($data, $request): void {
            $store = $this->access->lock($request->user());
            $this->access->module('purchasing');
            $supplier = Supplier::create($data);
            StoreAccess::audit($request->user(), $store->id, 'supplier.created', $supplier->id);
        });

        return back()->with('status', 'Supplier added.');
    }

    public function purchase(Request $request): RedirectResponse
    {
        $data = $request->validate(['request_key' => ['required', 'uuid'], 'supplier_id' => ['required', 'ulid'], 'notes' => ['nullable', 'string', 'max:2000'], 'lines' => ['required', 'array', 'min:1', 'max:100'], 'lines.*.product_id' => ['required', 'ulid'], 'lines.*.unit_id' => ['required', 'ulid'], 'lines.*.quantity' => ['required', 'string', 'max:20'], 'lines.*.cost' => ['required', 'string', 'max:20']]);
        $order = DB::transaction(function () use ($data, $request): PurchaseOrder {
            $store = $this->access->lock($request->user());
            $this->access->module('purchasing');
            $this->access->module('catalog');
            $this->access->module('inventory');
            $fingerprint = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
            if ($old = PurchaseOrder::where('request_key', $data['request_key'])->first()) {
                StoreAccess::check(hash_equals($old->fingerprint ?? '', $fingerprint), 'This purchase submission has already been used with different details.');

                return $old;
            }
            $supplier = Supplier::whereKey($data['supplier_id'])->firstOrFail();
            $order = PurchaseOrder::create(['fingerprint' => $fingerprint, 'supplier_id' => $supplier->id, 'supplier_name' => $supplier->name, 'request_key' => $data['request_key'], 'notes' => $data['notes'] ?? null]);
            $seen = [];
            foreach ($data['lines'] as $input) {
                $product = Product::where('active', true)->whereKey($input['product_id'])->firstOrFail();
                StoreAccess::check(! in_array($product->id, $seen), 'Combine duplicate products into one order line.');
                $seen[] = $product->id;
                $unit = ProductUnit::where('product_id', $product->id)->whereKey($input['unit_id'])->firstOrFail();
                $qty = $this->operations->quantity($product, $input['quantity'], (int) $unit->factor_micro);
                $cost = Amounts::ratio(Amounts::parse($input['cost'], 6), $qty, (int) $unit->factor_micro);
                PurchaseOrderLine::create(['purchase_order_id' => $order->id, 'product_id' => $product->id, 'name' => $product->name, 'sku' => $product->sku, 'unit_label' => $unit->label, 'factor_micro' => $unit->factor_micro, 'quantity_micro' => $qty, 'total_cost_micro' => $cost]);
            }
            StoreAccess::audit($request->user(), $store->id, 'purchase.drafted', $order->id);

            return $order;
        }, 3);

        return redirect()->route('store.order', [$this->context->id(), $order->id]);
    }

    public function order(string $store, string $order): View
    {
        $orderModel = PurchaseOrder::whereKey($order)->firstOrFail();

        return view('operations.order', ['store' => $this->context->store(), 'page' => 'purchasing', 'modules' => StoreModule::pluck('mode', 'module_key')->all(), 'order' => $orderModel, 'lines' => PurchaseOrderLine::where('purchase_order_id', $order)->get(), 'receipts' => BusinessDocument::where('metadata->order_id', $order)->get()]);
    }

    public function orderStatus(Request $request, string $store, string $order): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['placed', 'closed', 'cancelled'])], 'notes' => ['required', 'string', 'max:2000']]);
        DB::transaction(function () use ($data, $request, $order): void {
            $store = $this->access->lock($request->user());
            $this->access->module('purchasing', $data['status'] !== 'placed');
            $po = PurchaseOrder::whereKey($order)->firstOrFail();
            StoreAccess::check(($data['status'] === 'placed' && $po->status === 'draft') || (in_array($data['status'], ['closed', 'cancelled']) && in_array($po->status, ['draft', 'placed'])), 'This order cannot make that transition.');
            $po->update(['status' => $data['status']]);
            StoreAccess::audit($request->user(), $store->id, 'purchase.'.$data['status'], $po->id, ['reason' => $data['notes']]);
        });

        return back()->with('status', 'Purchase order updated.');
    }

    public function profile(Request $request): RedirectResponse
    {
        $data = $request->validate(['address_line_1' => ['required', 'string', 'max:255'], 'address_line_2' => ['nullable', 'string', 'max:255'], 'city' => ['required', 'string', 'max:255'], 'province' => ['nullable', 'string', 'max:255'], 'postal_code' => ['nullable', 'string', 'max:20'], 'contact_email' => ['nullable', 'email', 'max:255'], 'contact_phone' => ['nullable', 'string', 'max:40']]);
        DB::transaction(function () use ($data, $request): void {
            $store = $this->access->lock($request->user());
            $store->update($data);
            StoreAccess::audit($request->user(), $store->id, 'store.profile_updated', $store->id);
        });

        return back()->with('status', 'Store profile updated.');
    }
}
