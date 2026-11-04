<?php

namespace App\Http\Controllers;

use App\Models\BusinessDocument;
use App\Models\DocumentLine;
use App\Models\Payment;
use App\Services\CreditAccounts;
use App\Support\Tenancy\StoreContext;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReceiptController extends Controller
{
    public function show(Request $request, string $store, ?string $document = null): View
    {
        $request->validate(['paper' => ['nullable', 'in:document,58,80'], 'copy' => ['nullable', 'boolean']]);
        $context = app(StoreContext::class)->store();
        $doc = $document ? BusinessDocument::whereKey($document)->firstOrFail() : null;
        abort_if($doc && ! in_array($doc->kind, ['sale', 'return']), 404);

        return view('operations.receipt', [
            'store' => $context, 'doc' => $doc, 'paper' => $request->input('paper', 'document'),
            'copy' => $request->boolean('copy'), 'sample' => ! $doc,
            'lines' => $doc ? DocumentLine::where('business_document_id', $doc->id)->get() : collect([
                (object) ['name' => 'Sample long product name · Electrical wire 2.0 mm²', 'sku' => 'SAMPLE-001', 'quantity_micro' => 2500000, 'base_unit' => 'meter', 'unit_label' => 'meter', 'factor_micro' => 1000000, 'price_minor' => 1250, 'discount_minor' => 125, 'total_minor' => 3000],
                (object) ['name' => 'Sample nails · box of 100 pieces', 'sku' => 'SAMPLE-002', 'quantity_micro' => 100000000, 'base_unit' => 'piece', 'unit_label' => 'box', 'factor_micro' => 100000000, 'price_minor' => 20000, 'discount_minor' => 0, 'total_minor' => 20000],
            ]),
            'payments' => $doc ? Payment::where('business_document_id', $doc->id)->get() : collect([(object) ['method' => 'cash', 'amount_minor' => 23000, 'reference' => null]]),
            'balance' => $doc?->customer_id ? app(CreditAccounts::class)->balance($doc->customer_id, $doc->id) : null,
        ]);
    }
}
