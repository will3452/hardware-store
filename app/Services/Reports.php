<?php

namespace App\Services;

use App\Support\Amounts;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class Reports
{
    /**
     * @param  list<string>  $storeIds
     * @return array<string, int>
     */
    public function summarize(array $storeIds, string $from, string $to, string $timezone = 'Asia/Manila'): array
    {
        return DB::transaction(function () use ($storeIds, $from, $to, $timezone): array {
            $start = CarbonImmutable::parse($from, $timezone)->startOfDay()->utc();
            $end = CarbonImmutable::parse($to, $timezone)->addDay()->startOfDay()->utc();
            $rows = DB::table('business_documents')->whereIn('store_id', $storeIds)->where('posted_at', '>=', $start)->where('posted_at', '<', $end)
                ->selectRaw('kind, SUM(gross_minor) gross, SUM(discount_minor) discount, SUM(total_minor) total, SUM(cost_micro) cost, SUM(loss_micro) loss, SUM(gain_micro) gain, COUNT(*) count')->groupBy('kind')->get()->keyBy('kind');
            $value = fn (string $kind, string $key): int => (int) ($rows->get($kind)->$key ?? 0);
            $net = $value('sale', 'total') - $value('return', 'total');
            $cost = Amounts::ratio($value('sale', 'cost') - $value('return', 'cost'), 1, 10000);
            $expense = $value('expense', 'total') - $value('expense_reversal', 'total');
            $loss = Amounts::ratio((int) $rows->sum('loss'), 1, 10000);
            $inventory = DB::table('inventory_movements')->whereIn('store_id', $storeIds)->where('created_at', '<', $end)->sum('value_micro');

            $payments = DB::table('payments as p')->join('business_documents as d', function ($join): void {
                $join->on('d.id', '=', 'p.business_document_id')->on('d.store_id', '=', 'p.store_id');
            })->whereIn('d.store_id', $storeIds)->where('d.posted_at', '>=', $start)->where('d.posted_at', '<', $end)
                ->whereIn('d.kind', ['sale', 'collection', 'return', 'collection_reversal'])->selectRaw('d.kind, SUM(p.amount_minor) total')->groupBy('d.kind')->pluck('total', 'kind');
            $collections = (int) ($payments['sale'] ?? 0) + (int) ($payments['collection'] ?? 0);
            $refunds = (int) ($payments['return'] ?? 0) + (int) ($payments['collection_reversal'] ?? 0);
            $balances = DB::table('receivable_entries')->whereIn('store_id', $storeIds)->where('posted_at', '<', $end)->selectRaw('store_id, obligation_id, SUM(amount_minor) balance')->groupBy('store_id', 'obligation_id');
            $receivables = DB::query()->fromSub($balances, 'r')->join('business_documents as d', function ($join): void {
                $join->on('d.id', '=', 'r.obligation_id')->on('d.store_id', '=', 'r.store_id');
            })->selectRaw('COALESCE(SUM(r.balance), 0) outstanding, COALESCE(SUM(CASE WHEN d.due_date < ? THEN r.balance ELSE 0 END), 0) overdue', [$to])->first();

            return [
                'receivables' => (int) $receivables->outstanding, 'overdue_receivables' => (int) $receivables->overdue,
                'gross_sales' => $value('sale', 'gross'), 'discounts' => $value('sale', 'discount') - $value('return', 'discount'),
                'returns' => $value('return', 'gross'), 'net_sales' => $net, 'collections' => $collections,
                'refunds' => $refunds, 'net_payments' => $collections - $refunds, 'cost_of_goods' => $cost,
                'gross_profit' => $net - $cost, 'expenses' => $expense, 'inventory_losses' => $loss,
                'operating_estimate' => $net - $cost - $expense - $loss, 'inventory_gains' => Amounts::ratio((int) $rows->sum('gain'), 1, 10000),
                'inventory_value' => Amounts::ratio((int) $inventory, 1, 10000), 'sale_count' => $value('sale', 'count'),
            ];
        });
    }

    public function products(string $storeId, string $from, string $to, string $timezone): Builder
    {
        $start = CarbonImmutable::parse($from, $timezone)->startOfDay()->utc();
        $end = CarbonImmutable::parse($to, $timezone)->addDay()->startOfDay()->utc();

        return DB::table('document_lines as l')->join('business_documents as d', function ($join): void {
            $join->on('d.id', '=', 'l.business_document_id')->on('d.store_id', '=', 'l.store_id');
        })->where('l.store_id', $storeId)->whereIn('d.kind', ['sale', 'return'])->where('d.posted_at', '>=', $start)->where('d.posted_at', '<', $end)
            ->selectRaw("l.product_id, l.base_unit, MAX(l.name) as name, MAX(l.sku) as sku, SUM(CASE WHEN d.kind = 'sale' THEN l.quantity_micro ELSE -l.quantity_micro END) as quantity, SUM(CASE WHEN d.kind = 'sale' THEN l.total_minor ELSE -l.total_minor END) as sales, SUM(CASE WHEN d.kind = 'sale' THEN l.cost_micro ELSE -l.cost_micro END) as cost")
            ->groupBy('l.product_id', 'l.base_unit')->orderByDesc('sales')->orderBy('l.product_id');
    }

    public function stock(string $storeId, string $to, string $timezone): Builder
    {
        $end = CarbonImmutable::parse($to, $timezone)->addDay()->startOfDay()->utc();
        $movements = DB::table('inventory_movements')->where('store_id', $storeId)->where('created_at', '<', $end)->selectRaw('product_id, SUM(quantity_micro) quantity, SUM(value_micro) value')->groupBy('product_id');

        return DB::table('products as p')->leftJoinSub($movements, 'm', 'p.id', '=', 'm.product_id')->where('p.store_id', $storeId)->selectRaw('p.sku, p.name, p.base_unit, COALESCE(m.quantity, 0) as quantity, COALESCE(m.value, 0) as value')->orderBy('p.name')->orderBy('p.id');
    }

    public static function csvCell(string $value): string
    {
        return preg_match('/^[\s]*[=+@\-\t\r\n]/u', $value) ? "'".$value : $value;
    }
}
