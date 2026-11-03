<?php

namespace App\Services;

use App\Models\BusinessDocument;
use App\Models\Customer;
use App\Models\ReceivableEntry;
use App\Models\User;
use App\Support\Amounts;
use App\Support\Tenancy\StoreContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreditAccounts
{
    /** @param array<string, mixed> $data */
    public function saveCustomer(User $actor, array $data): Customer
    {
        return DB::transaction(function () use ($actor, $data): Customer {
            $access = app(StoreAccess::class);
            $store = $access->lock($actor);
            $access->module('customers');
            $customer = empty($data['customer_id']) ? new Customer : Customer::whereKey($data['customer_id'])->firstOrFail();
            if ($customer->exists) {
                StoreAccess::check((int) $data['revision'] === $customer->revision, 'This customer changed. Refresh before saving.');
                $customer->revision++;
            }
            $limit = isset($data['credit_limit']) ? Amounts::parse($data['credit_limit']) : ($customer->credit_limit_minor ?? 0);
            $enabled = (bool) ($data['credit_enabled'] ?? $customer->credit_enabled ?? false);
            if ($enabled && (! $customer->exists || ! $customer->credit_enabled || $limit !== $customer->credit_limit_minor)) {
                $access->module('credit');
                StoreAccess::check($limit > 0 && (! $customer->exists || $limit >= $this->balance($customer->id)), 'The credit limit must be positive and cover the current outstanding balance.');
            }
            $name = Str::squish($data['name']);
            StoreAccess::check($name !== '', 'Enter a customer name.');
            $customer->fill(['name' => $name, 'contact' => $data['contact'] ?? null, 'address' => $data['address'] ?? null, 'active' => $data['active'] ?? true, 'credit_enabled' => $enabled, 'credit_limit_minor' => $limit, 'term_days' => $data['term_days'] ?? 30]);
            $customer->save();
            StoreAccess::audit($actor, $store->id, 'customer.saved', $customer->id, ['credit_enabled' => $enabled, 'credit_limit_minor' => $limit, 'revision' => $customer->revision]);

            return $customer;
        }, 3);
    }

    public function balance(string $customer, ?string $obligation = null): int
    {
        return (int) ReceivableEntry::where('customer_id', $customer)->when($obligation, fn ($q) => $q->where('obligation_id', $obligation))->sum('amount_minor');
    }

    public function overdue(string $customer): int
    {
        return (int) DB::table('receivable_entries as r')->join('business_documents as d', function ($join): void {
            $join->on('d.id', '=', 'r.obligation_id')->on('d.store_id', '=', 'r.store_id');
        })->where('r.store_id', app(StoreContext::class)->id())->where('r.customer_id', $customer)->where('d.due_date', '<', now()->timezone(app(StoreContext::class)->store()->timezone)->toDateString())->sum('r.amount_minor');
    }

    /**
     * Called only inside the locked posting transaction.
     *
     * @param  array<string, mixed>  $data
     */
    public function sale(BusinessDocument $doc, array $data, int $paid): void
    {
        $unpaid = $doc->total_minor - $paid;
        $onCredit = ($data['settlement'] ?? 'paid') === 'credit';
        StoreAccess::check($unpaid === 0 || $onCredit, 'Payments must cover the full total. Choose customer credit explicitly for an unpaid balance.');
        if (empty($data['customer_id'])) {
            StoreAccess::check(! $onCredit, 'Select a customer for a credit sale.');

            return;
        }
        app(StoreAccess::class)->module('customers');
        $customer = Customer::where('active', true)->whereKey($data['customer_id'])->firstOrFail();
        if ($onCredit) {
            app(StoreAccess::class)->module('credit');
            StoreAccess::check($customer->credit_enabled && $this->balance($customer->id) + $unpaid <= $customer->credit_limit_minor, 'Customer credit is unavailable or this sale would exceed the credit limit.');
            StoreAccess::check(! empty($data['due_date']) && $data['due_date'] >= now()->timezone(app(StoreContext::class)->store()->timezone)->toDateString(), 'Review a due date on or after today.');
            $doc->due_date = $data['due_date'];
        }
        $doc->customer_id = $customer->id;
        $doc->metadata = array_merge($doc->metadata, ['customer' => $customer->name, 'settlement' => $onCredit ? 'credit' : 'paid', 'paid_at_sale_minor' => $paid, 'due_at_sale_minor' => $unpaid]);
        $this->entry($doc, $doc->id, 'charge', $doc->total_minor);
        $this->entry($doc, $doc->id, 'payment', -$paid);
    }

    /** @param array<string, mixed> $data */
    public function opening(BusinessDocument $doc, array $data): void
    {
        $customer = Customer::where('active', true)->whereKey($data['customer_id'])->firstOrFail();
        $amount = Amounts::parse($data['amount']);
        StoreAccess::check($amount > 0, 'Opening debt must be positive.');
        $doc->customer_id = $customer->id;
        $doc->due_date = $data['due_date'];
        $doc->total_minor = $amount;
        $doc->metadata = array_merge($doc->metadata, ['customer' => $customer->name, 'reference' => $data['reference'], 'original_date' => $data['original_date']]);
        $this->entry($doc, $doc->id, 'opening', $amount);
    }

    /** @param array<string, mixed> $data */
    public function allocate(BusinessDocument $doc, array $data): int
    {
        $customer = Customer::whereKey($data['customer_id'])->firstOrFail();
        $doc->customer_id = $customer->id;
        $doc->metadata = array_merge($doc->metadata, ['customer' => $customer->name]);
        $seen = [];
        $total = 0;
        foreach ($data['allocations'] as $allocation) {
            $amount = Amounts::parse($allocation['amount']);
            if ($amount === 0) {
                continue;
            }
            $obligation = BusinessDocument::where('customer_id', $customer->id)->whereIn('kind', ['sale', 'opening_receivable'])->whereKey($allocation['obligation_id'])->firstOrFail();
            StoreAccess::check(! isset($seen[$obligation->id]) && $amount <= $this->balance($customer->id, $obligation->id), 'An allocation exceeds the remaining debt or repeats a sale. Refresh the customer account.');
            $seen[$obligation->id] = true;
            $total += $amount;
            $this->entry($doc, $obligation->id, 'collection', -$amount);
        }
        StoreAccess::check($total > 0 && $total === Amounts::parse($data['amount']), 'The confirmed payment must equal the selected allocations and be positive.');
        $doc->total_minor = $total;

        return $total;
    }

    public function reduceForReturn(BusinessDocument $doc, BusinessDocument $original): int
    {
        if (! $original->customer_id) {
            return 0;
        }
        $doc->customer_id = $original->customer_id;
        $balance = $this->balance($original->customer_id, $original->id);
        $reduction = min($balance, $doc->total_minor);
        if ($reduction > 0) {
            app(StoreAccess::class)->module('credit', true);

        }
        $this->entry($doc, $original->id, 'return', -$doc->total_minor);
        $this->entry($doc, $original->id, 'refund', $doc->total_minor - $reduction);
        $doc->metadata = array_merge($doc->metadata, ['customer' => $original->metadata['customer'] ?? '', 'debt_reduction_minor' => $reduction, 'refund_minor' => $doc->total_minor - $reduction]);

        return $reduction;
    }

    public function reverse(BusinessDocument $doc, BusinessDocument $original): void
    {
        StoreAccess::check(! BusinessDocument::where('original_id', $original->id)->exists(), 'This record has already been reversed.');
        $entries = ReceivableEntry::where('business_document_id', $original->id)->get();
        foreach ($entries as $entry) {
            // Do not undo settlement after a dependent return/collection changed its meaning.
            $reversed = BusinessDocument::whereIn('kind', ['collection_reversal', 'opening_receivable_reversal'])->whereNotNull('original_id')->select('original_id');
            $later = ReceivableEntry::where('obligation_id', $entry->obligation_id)->where('id', '>', $entry->id)->where('business_document_id', '!=', $original->id)->where('kind', '!=', 'reversal')->whereNotIn('business_document_id', $reversed)->exists();
            StoreAccess::check(! $later, 'Later activity depends on this record. Resolve the later record first.');
        }
        $doc->customer_id = $original->customer_id;
        $doc->original_id = $original->id;
        $doc->total_minor = $original->total_minor;
        $doc->metadata = array_merge($doc->metadata, ['customer' => $original->metadata['customer'] ?? '']);
        foreach ($entries as $entry) {
            $this->entry($doc, $entry->obligation_id, 'reversal', -$entry->amount_minor);
        }
    }

    private function entry(BusinessDocument $doc, string $obligation, string $kind, int $amount): void
    {
        if ($amount !== 0) {
            ReceivableEntry::create(['customer_id' => $doc->customer_id, 'business_document_id' => $doc->id, 'obligation_id' => $obligation, 'kind' => $kind, 'amount_minor' => $amount, 'posted_at' => $doc->posted_at]);
        }
    }
}
