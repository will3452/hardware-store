<?php

namespace App\Http\Controllers;

use App\Models\CashSession;
use App\Models\Customer;
use App\Models\DocumentLine;
use App\Models\OrderRelease;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\StoreModule;
use App\Services\SalesOrders;
use App\Support\Amounts;
use App\Support\RecordPage;
use App\Support\Tenancy\StoreContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SalesOrderController extends Controller
{
    public function __construct(private StoreContext $context) {}

    public function index(Request $request): View
    {
        $request->validate(['q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::in(['draft', 'issued', 'accepted', 'partial', 'completed', 'closed', 'cancelled'])]]);
        $orders = SalesOrder::latest()->orderByDesc('id');
        if ($request->filled('q')) {
            $needle = '%'.addcslashes($request->string('q')->toString(), '\\%_').'%';
            $orders->where(fn ($q) => $q->where('id', 'like', $needle)->orWhere('customer_name', 'like', $needle)->orWhere('project', 'like', $needle));
        }
        if ($request->filled('status')) {
            $orders->where('status', $request->input('status'));
        }

        return view('operations.sales-orders', $this->common() + ['orders' => $orders->paginate(RecordPage::size($request))->withQueryString(), 'draftLines' => $this->draftLines()]);
    }

    public function show(string $store, string $salesOrder): View
    {
        $order = SalesOrder::whereKey($salesOrder)->firstOrFail();
        $lines = SalesOrderLine::where('sales_order_id', $order->id)->get();
        $products = Product::whereIn('id', $lines->pluck('product_id'))->get()->keyBy('id');

        $releases = OrderRelease::where('sales_order_id', $order->id)->latest('released_at')->paginate(RecordPage::size(request()), ['*'], 'release_page')->withQueryString();
        $returnedReleases = [];
        foreach ($releases as $release) {
            $sold = DocumentLine::where('business_document_id', $release->business_document_id);
            $returned = DocumentLine::whereIn('source_line_id', (clone $sold)->select('id'))->sum('quantity_micro');
            if ($returned >= (clone $sold)->sum('quantity_micro')) {
                $returnedReleases[] = $release->id;
            }
        }

        return view('operations.sales-order', $this->common() + ['order' => $order, 'lines' => $lines, 'products' => $products, 'draftLines' => $this->draftLines(array_values($lines->map(fn ($line) => ['product_id' => $line->product_id, 'unit_id' => $line->product_unit_id, 'quantity' => Amounts::display(Amounts::ratio($line->quantity_micro, 1000000, $line->factor_micro), 6), 'price' => Amounts::display($line->price_minor), 'discount' => Amounts::display($line->discount_minor), 'discount_type' => 'amount'])->all())), 'releases' => $releases, 'returnedReleases' => $returnedReleases, 'replacement' => SalesOrder::where('replaces_id', $order->id)->first(), 'customer' => Customer::whereKey($order->customer_id)->firstOrFail(), 'session' => CashSession::whereNull('closed_at')->first()]);
    }

    public function action(Request $request, string $store, string $action, ?string $salesOrder = null): RedirectResponse
    {
        abort_unless(in_array($action, ['save', 'issue', 'accept', 'close', 'revise', 'release', 'receive']), 404);
        abort_unless($salesOrder || $action === 'save', 404);
        $rules = ['request_key' => ['required', 'uuid'], 'revision' => [$salesOrder ? 'required' : 'nullable', 'integer', 'min:1'], 'notes' => [in_array($action, ['accept', 'close', 'revise']) ? 'required' : 'nullable', 'string', 'max:1500']];
        $rules += match ($action) {
            'save' => ['customer_id' => ['required', 'ulid'], 'valid_until' => ['required', 'date_format:Y-m-d'], 'project' => ['nullable', 'string', 'max:255'], 'lines' => ['required', 'array', 'min:1', 'max:100'], 'lines.*.product_id' => ['required', 'ulid'], 'lines.*.unit_id' => ['required', 'ulid'], 'lines.*.quantity' => ['required', 'string', 'max:20'], 'lines.*.price' => ['required', 'string', 'max:20'], 'lines.*.discount' => ['required', 'string', 'max:20'], 'lines.*.discount_type' => ['required', Rule::in(['amount', 'percent'])]],
            'release' => ['lines' => ['required', 'array', 'min:1', 'max:100'], 'lines.*.order_line_id' => ['required', 'ulid'], 'lines.*.quantity' => ['required', 'string', 'max:20'], 'cash_session_id' => ['required', 'ulid'], 'settlement' => ['required', Rule::in(['paid', 'credit'])], 'due_date' => ['nullable', 'required_if:settlement,credit', 'date_format:Y-m-d'], 'payments' => ['required', 'array', 'min:1', 'max:4'], 'payments.*.method' => ['required', Rule::in(['cash', 'bank', 'card', 'ewallet'])], 'payments.*.amount' => ['required', 'string', 'max:20'], 'payments.*.reference' => ['nullable', 'string', 'max:255'], 'payments.*.confirmed' => ['nullable', 'boolean'], 'method' => ['required', Rule::in(['pickup', 'delivery'])], 'recipient' => ['required', 'string', 'max:255'], 'address' => ['nullable', 'required_if:method,delivery', 'string', 'max:1000'], 'reference' => ['nullable', 'string', 'max:1000']],
            'receive' => ['release_id' => ['required', 'ulid'], 'recipient' => ['required', 'string', 'max:255']],
            default => [],
        };
        $data = $request->validate($rules);
        $order = app(SalesOrders::class)->act($request->user(), $action, $salesOrder, $data);

        return to_route('store.sales-order', [$store, $order->id])->with('status', 'Order action saved. Review the current status and linked records below.');
    }

    /** @param list<array<string, mixed>> $defaults
     * @return list<array<string, mixed>>
     */
    private function draftLines(array $defaults = []): array
    {
        $inputs = session()->getOldInput('_form') === 'quote-draft' ? session()->getOldInput('lines', []) : $defaults;
        $result = [];
        foreach (array_slice($inputs, 0, 100) as $input) {
            $product = Product::whereKey($input['product_id'] ?? '')->first();
            if (! $product) {
                continue;
            }
            $result[] = $input + ['name' => $product->name, 'sku' => $product->sku, 'base_unit' => $product->base_unit, 'fractional' => $product->fractional, 'units' => ProductUnit::where('product_id', $product->id)->get()->toArray(), 'basePrice' => $product->price_minor, 'catalog_price_minor' => $product->price_minor];
        }

        return $result;
    }

    /** @return array<string, mixed> */
    private function common(): array
    {
        return ['store' => $this->context->store(), 'page' => 'orders', 'modules' => StoreModule::pluck('mode', 'module_key')->all()];
    }
}
