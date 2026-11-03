<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\BusinessDocument;
use App\Models\CashSession;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\StoreModule;
use App\Support\Tenancy\StoreContext;

/**
 * @phpstan-type Step array{key: string, title: string, description: string, page: string, anchor: string, action: string, done: bool}
 * @phpstan-type Progress array{steps: list<Step>, completed: int, total: int, percent: int, complete: bool, next: Step|null, expanded: bool}
 */
final class StoreOnboarding
{
    /** @return Progress */
    public function progress(): array
    {
        $storeId = app(StoreContext::class)->id();
        $modules = StoreModule::pluck('mode', 'module_key')->all();
        $profileReviewed = AuditEvent::where('store_id', $storeId)->where('action', 'store.profile_updated')->exists();
        $paymentsReviewed = AuditEvent::where('store_id', $storeId)->where('action', 'store.payment_methods_updated')->exists();
        $firstSale = BusinessDocument::where('kind', 'sale')->exists();

        $definitions = [
            ['profile', 'Review your store details', 'Check your address and contact information, then save the contact details.', 'profile', 'store-details', 'Review store details', [], $profileReviewed],
            ['payments', 'Choose accepted payments', 'Select how customers can pay, then save your payment methods.', 'profile', 'payment-settings', 'Choose payment methods', ['sales'], $paymentsReviewed],
            ['products', 'Add your first product', 'Enter a product name, selling unit, and price. Your SKU can be generated automatically.', 'products', 'add-product', 'Add a product', ['catalog'], Product::exists()],
            ['inventory', 'Record starting stock', 'Enter actual opening quantities and reviewed costs, or receive your first purchase delivery.', 'inventory', 'stock-products', 'Enter opening stock', ['catalog', 'inventory'], InventoryMovement::where('quantity_micro', '>', 0)->whereIn('business_document_id', BusinessDocument::select('id')->whereIn('kind', ['adjustment', 'receipt']))->exists()],
            ['cash', 'Open your first cash drawer', 'Count the starting cash. Enter zero if the drawer is empty.', 'cash', '', 'Open cash drawer', ['cash'], CashSession::exists()],
            ['sale', 'Record your first real sale', 'When a customer buys, post the sale with its payment or approved customer credit. There is no need to create a test sale to finish setup.', 'sales', '', 'Go to point of sale', ['catalog', 'inventory', 'cash', 'sales'], $firstSale],
        ];

        $steps = [];
        $completed = 0;
        $next = null;
        foreach ($definitions as [$key, $title, $description, $page, $anchor, $action, $requiredModules, $done]) {
            if (array_filter($requiredModules, fn ($module) => ($modules[$module] ?? null) !== 'enabled')) {
                continue;
            }
            $step = compact('key', 'title', 'description', 'page', 'anchor', 'action', 'done');
            $steps[] = $step;
            $completed += (int) $done;
            if (! $done && $next === null) {
                $next = $step;
            }
        }
        $total = count($steps);
        $complete = $completed === $total;

        return ['steps' => $steps, 'completed' => $completed, 'total' => $total, 'percent' => (int) round($completed / $total * 100), 'complete' => $complete, 'next' => $next, 'expanded' => ! $complete && ! $firstSale];
    }
}
