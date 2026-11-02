<?php

namespace App\Http\Controllers;

use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\StoreModule;
use App\Services\Reports;
use App\Services\StoreAccess;
use App\Support\Amounts;
use App\Support\Tenancy\StoreContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CashReportController extends Controller
{
    public function show(Request $request, string $store, string $cashSession, StoreContext $context): View|StreamedResponse
    {
        $session = CashSession::whereKey($cashSession)->firstOrFail();
        $storeModel = $context->store();
        if ($request->boolean('export')) {
            StoreAccess::audit($request->user(), $store, 'cash.session_exported', $session->id);

            return response()->streamDownload(function () use ($session, $storeModel): void {
                DB::transaction(function () use ($session, $storeModel): void {
                    // Streaming runs after the tenant middleware clears request context.
                    $snapshot = CashSession::withoutGlobalScopes()->where('store_id', $storeModel->id)->whereKey($session->id)->firstOrFail();
                    $out = fopen('php://output', 'w');
                    if ($out === false) {
                        throw new \RuntimeException('Cannot open cash report output.');
                    }
                    fputcsv($out, ['Store', Reports::csvCell($storeModel->display_name), 'Currency', $storeModel->currency, 'Timezone', $storeModel->timezone, 'Session', $session->id], escape: '');
                    fputcsv($out, ['Opening', Amounts::display($snapshot->opening_minor), 'Expected', Amounts::display($snapshot->expected_minor), 'Counted', $snapshot->counted_minor === null ? '' : Amounts::display($snapshot->counted_minor), 'Variance', $snapshot->variance_minor === null ? '' : Amounts::display($snapshot->variance_minor)], escape: '');
                    fputcsv($out, ['Posted', 'Reason', 'Cash in', 'Cash out', 'Record'], escape: '');
                    foreach (CashMovement::withoutGlobalScopes()->where('store_id', $storeModel->id)->where('cash_session_id', $session->id)->orderBy('created_at')->orderBy('id')->cursor() as $movement) {
                        fputcsv($out, [$movement->created_at->timezone($storeModel->timezone)->format('Y-m-d H:i:s'), Reports::csvCell($movement->reason), Amounts::display(max(0, $movement->amount_minor)), Amounts::display(max(0, -$movement->amount_minor)), $movement->business_document_id ?? 'Manual'], escape: '');
                    }
                    fclose($out);
                });
            }, 'cash-session-'.$session->id.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        return DB::transaction(function () use ($session, $storeModel): View {
            $session->refresh();
            $query = CashMovement::where('cash_session_id', $session->id);
            $totals = (clone $query)->selectRaw('COALESCE(SUM(CASE WHEN amount_minor > 0 THEN amount_minor ELSE 0 END), 0) cash_in, COALESCE(SUM(CASE WHEN amount_minor < 0 THEN -amount_minor ELSE 0 END), 0) cash_out')->first();

            return view('operations.cash-session', ['store' => $storeModel, 'page' => 'cash', 'modules' => StoreModule::pluck('mode', 'module_key')->all(), 'cashSession' => $session,
                'cashIn' => (int) $totals?->getAttribute('cash_in'), 'cashOut' => (int) $totals?->getAttribute('cash_out'), 'movements' => $query->orderBy('created_at')->orderBy('id')->paginate(50)]);
        });
    }
}
