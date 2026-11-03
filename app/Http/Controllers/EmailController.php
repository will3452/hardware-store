<?php

namespace App\Http\Controllers;

use App\Models\EmailDelivery;
use App\Services\EmailOutbox;
use App\Services\StoreAccess;
use App\Support\RecordPage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EmailController extends Controller
{
    public function index(Request $request, EmailOutbox $outbox): View
    {
        StoreAccess::requirePermission($request->user(), 'platform.stores.manage');
        $request->validate(['status' => ['nullable', Rule::in(['pending', 'sending', 'sent', 'failed', 'cancelled'])], 'store_id' => ['nullable', 'ulid']]);
        $query = EmailDelivery::query();
        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }
        if ($request->filled('store_id')) {
            $query->where('store_id', $request->string('store_id')->toString());
        }

        return view('admin.email', ['deliveries' => $query->latest()->orderByDesc('id')->paginate(RecordPage::size($request))->withQueryString(), 'configured' => $outbox->configured(),
            'counts' => EmailDelivery::selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status'),
            'lastSent' => EmailDelivery::whereNotNull('sent_at')->latest('sent_at')->first()?->sent_at]);
    }

    public function retry(Request $request, string $delivery, EmailOutbox $outbox): RedirectResponse
    {
        StoreAccess::requirePermission($request->user(), 'platform.stores.manage');
        $outbox->retry(EmailDelivery::findOrFail($delivery), $request->user());

        return back()->with('status', 'Email queued for another delivery attempt.');
    }

    public function test(Request $request, EmailOutbox $outbox): RedirectResponse
    {
        StoreAccess::requirePermission($request->user(), 'platform.stores.manage');
        StoreAccess::check($outbox->configured(), 'Configure SMTP before sending a test.');
        $delivery = $outbox->enqueue('test', $request->user()->email, [
            'title' => 'Email delivery test', 'body' => 'This is a Pyesio delivery test. Your SMTP connection is working. No account links or store data are included.',
        ], user: $request->user(), expires: now()->addHour());
        StoreAccess::audit($request->user(), null, 'email.test_requested', $delivery->id);

        return back()->with('status', 'A test email is queued for your signed-in account’s email address.');
    }
}
