<?php

namespace App\Http\Controllers;

use App\Models\BusinessDocument;
use App\Models\CashSession;
use App\Models\Customer;
use App\Models\ReceivableEntry;
use App\Models\StoreModule;
use App\Services\CreditAccounts;
use App\Services\Reports;
use App\Services\StoreAccess;
use App\Support\Amounts;
use App\Support\RecordPage;
use App\Support\Tenancy\StoreContext;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerController extends Controller
{
    public function __construct(private StoreContext $context) {}

    public function index(Request $request): View
    {
        $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $customers = Customer::orderBy('name')->orderBy('id');
        if ($request->filled('q')) {
            $needle = '%'.addcslashes($request->string('q')->toString(), '\\%_').'%';
            $customers->where(fn ($q) => $q->where('name', 'like', $needle)->orWhere('contact', 'like', $needle));
        }

        return view('operations.customers', $this->common() + ['customers' => $customers->paginate(RecordPage::size($request))->withQueryString()]);
    }

    public function search(Request $request): JsonResponse
    {
        app(StoreAccess::class)->module('customers');
        $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $query = '%'.addcslashes($request->string('q')->toString(), '\\%_').'%';
        $customers = Customer::where('active', true)->where('name', 'like', $query)->orderBy('name')->limit(20)->get();

        return response()->json($customers->map(fn ($customer) => ['id' => $customer->id, 'name' => $customer->name, 'credit_enabled' => $customer->credit_enabled, 'limit' => $customer->credit_limit_minor, 'balance' => app(CreditAccounts::class)->balance($customer->id), 'term_days' => $customer->term_days, 'overdue' => app(CreditAccounts::class)->overdue($customer->id)]));
    }

    public function save(Request $request): RedirectResponse
    {
        $data = $request->validate(['customer_id' => ['nullable', 'ulid'], 'revision' => ['required_with:customer_id', 'integer', 'min:1'], 'name' => ['required', 'string', 'max:255'], 'contact' => ['nullable', 'string', 'max:255'], 'address' => ['nullable', 'string', 'max:1000'], 'active' => ['nullable', 'boolean'], 'credit_enabled' => ['nullable', 'boolean'], 'credit_limit' => ['nullable', 'string', 'regex:/^\d{1,9}(\.\d{1,2})?$/D'], 'term_days' => ['required', 'integer', 'min:0', 'max:365']]);
        $customer = app(CreditAccounts::class)->saveCustomer($request->user(), $data);

        return to_route('store.customer', [$this->context->id(), $customer->id])->with('status', 'Customer saved.');
    }

    public function show(Request $request, string $store, string $customer): View|StreamedResponse
    {
        $record = Customer::findOrFail($customer);
        $request->validate(['from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from']]);
        $to = $request->input('to', now()->timezone($this->context->store()->timezone)->toDateString());
        $from = $request->input('from', CarbonImmutable::parse($to)->startOfMonth()->toDateString());
        StoreAccess::check($from <= $to, 'The end date must follow the start date.');
        $start = CarbonImmutable::parse($from, $this->context->store()->timezone)->startOfDay()->utc();
        $end = CarbonImmutable::parse($to, $this->context->store()->timezone)->addDay()->startOfDay()->utc();
        $base = DB::table('receivable_entries as r')->join('business_documents as d', function ($join): void {
            $join->on('d.id', '=', 'r.business_document_id')->on('d.store_id', '=', 'r.store_id');
        })->where('r.store_id', $store)->where('r.customer_id', $customer);
        $opening = (int) (clone $base)->where('r.posted_at', '<', $start)->sum('r.amount_minor');
        $closing = (int) (clone $base)->where('r.posted_at', '<', $end)->sum('r.amount_minor');
        $entries = (clone $base)->where('r.posted_at', '>=', $start)->where('r.posted_at', '<', $end)->orderBy('r.posted_at')->orderBy('r.id')->select('r.*', 'd.kind as document_kind', 'd.due_date');
        $balances = ReceivableEntry::selectRaw('obligation_id, SUM(amount_minor) balance')->groupBy('obligation_id');
        $outstanding = BusinessDocument::joinSub($balances, 'r', 'business_documents.id', '=', 'r.obligation_id')->where('customer_id', $customer)->where('r.balance', '>', 0)->orderBy('due_date')->orderBy('business_documents.id')->select('business_documents.*', 'r.balance');
        $overdue = (int) (clone $outstanding)->where('due_date', '<', now()->timezone($this->context->store()->timezone)->toDateString())->sum('r.balance');
        if ($request->filled('export')) {
            app(StoreAccess::class)->history('reports');
            StoreAccess::audit($request->user(), $store, 'customer.statement_exported', $customer, compact('from', 'to'));
            $storeModel = $this->context->store();

            return response()->streamDownload(function () use ($entries, $base, $start, $end, $record, $storeModel, $from, $to): void {
                DB::transaction(function () use ($entries, $base, $start, $end, $record, $storeModel, $from, $to): void {
                    $opening = (int) (clone $base)->where('r.posted_at', '<', $start)->sum('r.amount_minor');
                    $closing = (int) (clone $base)->where('r.posted_at', '<', $end)->sum('r.amount_minor');
                    $out = fopen('php://output', 'w');
                    if ($out === false) {
                        throw new \RuntimeException('Cannot open output.');
                    }
                    fputcsv($out, ['Store', Reports::csvCell($storeModel->display_name), 'Customer', Reports::csvCell($record->name), 'Currency', $storeModel->currency, 'Timezone', $storeModel->timezone, 'From', $from, 'To', $to, 'Opening', Amounts::display($opening), 'Closing', Amounts::display($closing)], escape: '');
                    fputcsv($out, ['Posted', 'Activity', 'Document', 'Original obligation', 'Charge', 'Reduction'], escape: '');
                    foreach ($entries->cursor() as $entry) {
                        fputcsv($out, [CarbonImmutable::parse($entry->posted_at)->timezone($storeModel->timezone)->toDateTimeString(), $entry->kind, $entry->business_document_id, $entry->obligation_id, Amounts::display($entry->amount_minor > 0 ? $entry->amount_minor : 0), Amounts::display($entry->amount_minor < 0 ? ltrim((string) $entry->amount_minor, '-') : 0)], escape: '');
                    }
                    fclose($out);
                });
            }, 'customer-statement.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        return view('operations.customer', $this->common() + ['customer' => $record, 'from' => $from, 'to' => $to, 'opening' => $opening, 'closing' => $closing, 'balance' => app(CreditAccounts::class)->balance($customer), 'overdue' => $overdue,
            'entries' => $entries->paginate(RecordPage::size($request), ['*'], 'statement_page')->withQueryString(), 'obligations' => $outstanding->paginate(RecordPage::size($request), ['*'], 'debt_page')->withQueryString(), 'session' => CashSession::whereNull('closed_at')->first()]);
    }

    /** @return array<string, mixed> */
    private function common(): array
    {
        return ['store' => $this->context->store(), 'page' => 'customers', 'modules' => StoreModule::pluck('mode', 'module_key')->all()];
    }
}
