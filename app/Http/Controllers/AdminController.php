<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\AuditEvent;
use App\Models\EmailDelivery;
use App\Models\Store;
use App\Models\StoreInvitation;
use App\Models\StoreMembership;
use App\Models\User;
use App\Services\EmailOutbox;
use App\Services\Invitations;
use App\Services\RecoverySnapshots;
use App\Services\Reports;
use App\Services\StoreAccess;
use App\Support\Amounts;
use App\Support\RecordPage;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminController extends Controller
{
    public function health(Request $request, RecoverySnapshots $snapshots): View
    {
        StoreAccess::requirePermission($request->user(), 'platform.stores.manage');
        $heartbeat = Cache::get('scheduler:last_seen');
        $backup = $snapshots->latest();
        $mailReady = app(EmailOutbox::class)->configured();
        $lastEmail = EmailDelivery::whereNotNull('sent_at')->latest('sent_at')->first();
        $mailFailures = EmailDelivery::where('status', 'failed')->count();
        $checks = [
            ['label' => 'Scheduled tasks', 'ready' => is_string($heartbeat) && CarbonImmutable::parse($heartbeat)->isAfter(now()->subMinutes(5)), 'detail' => is_string($heartbeat) ? 'Last heartbeat: '.CarbonImmutable::parse($heartbeat)->timezone('Asia/Manila')->format('M j, Y H:i:s') : 'No scheduler heartbeat received. Configure the every-minute task in hosting.'],
            ['label' => 'Recovery snapshots', 'ready' => $backup && $backup['verified_at'] && CarbonImmutable::parse($backup['created_at'])->isAfter(now()->subHours(26)), 'detail' => $backup ? 'Latest snapshot: '.CarbonImmutable::parse($backup['created_at'])->timezone('Asia/Manila')->format('M j, Y H:i:s').($backup['verified_at'] ? ' · checksums verified' : ' · verification pending') : 'No complete recovery snapshot found.'],
            ['label' => 'Account email delivery', 'ready' => $mailReady && $lastEmail && $mailFailures === 0, 'detail' => ! $mailReady ? 'Email is not configured. Share activation and recovery links privately from store management.' : ($lastEmail ? 'Latest SMTP acceptance: '.$lastEmail->sent_at?->timezone('Asia/Manila')->format('M j, Y H:i').' · '.$mailFailures.' failed deliveries. Review Email delivery for details.' : 'SMTP configured. Send a test from Email delivery.')],
            ['label' => 'Production error display', 'ready' => ! config('app.debug'), 'detail' => config('app.debug') ? 'Detailed error display is enabled.' : 'Detailed error display is disabled.'],
            ['label' => 'Background jobs', 'ready' => DB::table('failed_jobs')->count() === 0, 'detail' => DB::table('failed_jobs')->count().' failed jobs awaiting review.'],
        ];

        return view('admin.health', compact('checks'));
    }

    public function index(Request $request, Reports $reports): View|StreamedResponse
    {
        $request->validate(['store_q' => ['nullable', 'string', 'max:100'], 'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'], 'store_id' => ['nullable', 'ulid', 'exists:stores,id']]);
        $perPage = RecordPage::size($request);
        $stores = Store::orderBy('display_name')->orderBy('id')->get();
        $from = $request->input('from', now()->timezone('Asia/Manila')->format('Y-m-d'));
        $to = $request->input('to', $from);
        StoreAccess::check($to >= $from, 'The end date must follow the start date.');
        $metrics = [];
        if ($request->user()->hasPlatformPermission('platform.reports.read')) {
            DB::transaction(function () use ($stores, $request, $reports, $from, $to, &$metrics): void {
                foreach ($stores as $store) {
                    if (! $request->filled('store_id') || $request->input('store_id') === $store->id) {
                        $metrics[$store->id] = $reports->summarize([$store->id], $from, $to);
                    }
                }
            });
        }
        if ($request->user()->hasPlatformPermission('platform.reports.read')) {
            StoreAccess::audit($request->user(), null, $request->boolean('export') ? 'platform.report_exported' : 'platform.report_viewed', null, ['from' => $from, 'to' => $to, 'store_ids' => array_keys($metrics)]);
        }
        if ($request->boolean('export')) {
            StoreAccess::requirePermission($request->user(), 'platform.reports.read');
            StoreAccess::requirePermission($request->user(), 'platform.reports.export');

            return response()->streamDownload(function () use ($stores, $metrics, $from, $to): void {
                $out = fopen('php://output', 'w');
                if ($out === false) {
                    throw new \RuntimeException('Cannot open report output.');
                }
                fputcsv($out, ['Store', 'Status', 'Currency', 'Timezone', 'From', 'To', 'Metric', 'Value'], escape: '');
                foreach ($stores as $store) {
                    foreach ($metrics[$store->id] ?? [] as $metric => $amount) {
                        fputcsv($out, [Reports::csvCell($store->display_name), $store->status, $store->currency, 'Asia/Manila', $from, $to, $metric, $metric === 'sale_count' ? $amount : Amounts::display($amount)], escape: '');
                    }
                }
                fclose($out);
            }, 'platform-report.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        $directory = Store::orderBy('display_name')->orderBy('id');
        if ($request->filled('store_q')) {
            $needle = '%'.addcslashes($request->string('store_q')->toString(), '\\%_').'%';
            $directory->where(fn ($q) => $q->where('display_name', 'like', $needle)->orWhere('code', 'like', $needle)->orWhere('city', 'like', $needle));
        }
        if ($request->filled('store_id')) {
            $directory->whereKey($request->input('store_id'));
        }
        $storeRecords = $directory->paginate($perPage)->withQueryString();

        return view('admin.index', compact('stores', 'storeRecords', 'metrics', 'from', 'to'));
    }

    public function create(Request $request, Invitations $invitations): RedirectResponse
    {
        StoreAccess::requirePermission($request->user(), 'platform.stores.manage');
        StoreAccess::requirePermission($request->user(), 'platform.modules.manage');
        $data = $request->validate([
            'code' => ['required', 'alpha_dash', 'max:40', 'unique:stores,code'], 'display_name' => ['required', 'string', 'max:255'],
            'address_line_1' => ['required', 'string', 'max:255'], 'city' => ['required', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:255'], 'contact_phone' => ['nullable', 'string', 'max:40'],
            'name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255'], 'existing_identity' => ['nullable', 'boolean'],
            'modules' => ['required', 'array'], 'modules.*' => [Rule::in(StoreAccess::MODULES)],
        ]);
        $link = DB::transaction(function () use ($data, $request, $invitations): string {
            $this->dependencies(array_fill_keys($data['modules'], 'enabled'));
            $store = Store::create(array_intersect_key($data, array_flip(['code', 'display_name', 'address_line_1', 'city', 'province', 'contact_phone'])) + ['currency' => 'PHP', 'timezone' => 'Asia/Manila', 'contact_email' => $data['email']]);
            foreach (StoreAccess::MODULES as $key) {
                DB::table('store_modules')->insert(['id' => (string) Str::ulid(), 'store_id' => $store->id, 'module_key' => $key, 'mode' => in_array($key, $data['modules']) ? 'enabled' : 'disabled', 'created_at' => now(), 'updated_at' => now()]);
            }
            StoreAccess::audit($request->user(), $store->id, 'store.created', $store->id);

            return $invitations->issue($store, $request->user(), $data);
        }, 3);

        return redirect()->route('admin.dashboard')->with('status', 'Store created. The invitation is queued for email. The activation link is also available to share privately.')->with('invitation_link', $link);
    }

    public function show(Request $request, string $store): View
    {
        StoreAccess::requirePermission($request->user(), 'platform.stores.manage');
        $model = Store::findOrFail($store);
        $memberships = StoreMembership::where('store_id', $store)->get();

        return view('admin.store', ['store' => $model, 'modules' => DB::table('store_modules')->where('store_id', $store)->pluck('mode', 'module_key')->all(), 'memberships' => $memberships, 'users' => User::whereIn('id', $memberships->pluck('user_id'))->get()->keyBy('id'), 'invitations' => StoreInvitation::where('store_id', $store)->latest()->get(), 'deliveries' => EmailDelivery::where('store_id', $store)->where('kind', 'invitation')->get()->keyBy('source_id')]);
    }

    public function update(Request $request, string $store): RedirectResponse
    {
        StoreAccess::requirePermission($request->user(), 'platform.stores.manage');
        $data = $request->validate(['display_name' => ['required', 'string', 'max:255'], 'address_line_1' => ['required', 'string', 'max:255'], 'city' => ['required', 'string', 'max:255'], 'status' => ['required', Rule::in(['pending_activation', 'active', 'suspended', 'archived'])], 'reason' => ['required', 'string', 'max:1000']]);
        DB::transaction(function () use ($data, $store, $request): void {
            $model = Store::whereKey($store)->lockForUpdate()->firstOrFail();
            $hasOwner = StoreMembership::where('store_id', $store)->where('status', 'active')->exists();
            StoreAccess::check($data['status'] !== 'active' || $hasOwner, 'The owner must accept an activation link first.');
            StoreAccess::check($data['status'] !== 'pending_activation' || ! $hasOwner, 'An activated store cannot become pending again.');
            $before = $model->status;
            $model->update(array_diff_key($data, ['reason' => true]));
            StoreAccess::audit($request->user(), $store, 'store.updated', $store, ['before_status' => $before, 'status' => $model->status, 'reason' => $data['reason']]);
        });

        return back()->with('status', 'Store updated.');
    }

    public function invite(Request $request, string $store, Invitations $invitations): RedirectResponse
    {
        StoreAccess::requirePermission($request->user(), 'platform.stores.manage');
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255'], 'existing_identity' => ['nullable', 'boolean']]);
        $link = DB::transaction(fn () => $invitations->issue(Store::whereKey($store)->lockForUpdate()->firstOrFail(), $request->user(), $data));

        return back()->with('status', 'New invitation queued for email; earlier pending links are revoked. The current owner retains access until acceptance.')->with('invitation_link', $link);
    }

    public function recovery(Request $request, string $store): RedirectResponse
    {
        StoreAccess::requirePermission($request->user(), 'platform.stores.manage');
        $request->validate(['confirm' => ['accepted'], 'delivery' => ['nullable', Rule::in(['email', 'link'])]]);
        $link = DB::transaction(function () use ($request, $store): string {
            Store::whereKey($store)->lockForUpdate()->firstOrFail();
            $membership = StoreMembership::where('store_id', $store)->where('status', 'active')->where('role', 'owner')->firstOrFail();
            $owner = User::findOrFail($membership->user_id);
            // Platform administrator recovery stays outside merchant administration.
            abort_if($owner->platformPermissions()->exists(), 403, 'Use the platform account recovery process for this identity.');
            StoreAccess::check($owner->status === 'active', 'This account is disabled.');
            $token = Password::broker()->createToken($owner);
            if ($request->input('delivery') === 'email') {
                $owner->sendPasswordResetNotification($token);
            }
            StoreAccess::audit($request->user(), $store, 'owner.recovery_link_created', $owner->id);

            return route('password.reset', ['token' => $token, 'email' => $owner->email]);
        });

        if ($request->input('delivery') === 'email') {
            return back()->with('status', 'A password reset email is queued for the active owner.');
        }

        return back()->with('recovery_link', $link)->with('status', 'Share this reset link only after verifying the owner’s identity. It expires in 60 minutes.');
    }

    public function modules(Request $request, string $store): RedirectResponse
    {
        StoreAccess::requirePermission($request->user(), 'platform.modules.manage');
        $data = $request->validate(['modules' => ['required', 'array'], 'modules.*' => [Rule::in(['enabled', 'restricted', 'disabled'])]]);
        DB::transaction(function () use ($data, $store, $request): void {
            Store::whereKey($store)->lockForUpdate()->firstOrFail();
            $modes = array_intersect_key($data['modules'], array_flip(StoreAccess::MODULES));
            StoreAccess::check(count($modes) === count(StoreAccess::MODULES), 'Select every module mode.');
            $this->dependencies($modes);
            foreach ($modes as $key => $mode) {
                if ($mode === 'disabled') {
                    $used = match ($key) {
                        'customers', 'credit' => DB::table('receivable_entries')->where('store_id', $store)->exists(),
                        'orders' => DB::table('sales_orders')->where('store_id', $store)->whereNotIn('status', ['cancelled', 'completed', 'closed'])->exists()
                            || DB::table('order_releases')->where('store_id', $store)->where('method', 'delivery')->whereNull('received_at')->exists(),
                        'cash' => DB::table('cash_sessions')->where('store_id', $store)->whereNull('closed_at')->exists(),
                        'purchasing' => DB::table('purchase_orders')->where('store_id', $store)->whereIn('status', ['draft', 'placed'])->exists() || DB::table('business_documents')->where('store_id', $store)->where('kind', 'receipt')->exists(),
                        'sales' => DB::table('business_documents')->where('store_id', $store)->where('kind', 'sale')->exists(),
                        'expenses' => DB::table('business_documents')->where('store_id', $store)->where('kind', 'expense')->exists(),
                        'inventory' => DB::table('inventory_movements')->where('store_id', $store)->exists(),
                        default => false,
                    };
                    StoreAccess::check(! $used, 'Use restricted mode for '.$key.' so existing records can be resolved.');
                }
                DB::table('store_modules')->updateOrInsert(['store_id' => $store, 'module_key' => $key], ['id' => DB::table('store_modules')->where('store_id', $store)->where('module_key', $key)->value('id') ?? (string) Str::ulid(), 'mode' => $mode, 'updated_at' => now(), 'created_at' => now()]);
            }
            StoreAccess::audit($request->user(), $store, 'modules.updated', $store, $modes);
        });

        return back()->with('status', 'Module access updated.');
    }

    /** @param array<string, string> $modes */
    private function dependencies(array $modes): void
    {
        foreach (StoreAccess::DEPENDENCIES as $module => $dependencies) {
            foreach ($dependencies as $dependency) {
                if (($modes[$module] ?? 'disabled') === 'enabled') {
                    StoreAccess::check(($modes[$dependency] ?? 'disabled') === 'enabled', ucfirst($module).' requires '.$dependency.' enabled.');
                }
                if (($modes[$module] ?? 'disabled') === 'restricted' && $dependency !== 'catalog') {
                    StoreAccess::check(in_array($modes[$dependency] ?? 'disabled', ['restricted', 'enabled']), ucfirst($module).' resolution requires '.$dependency.' available.');
                }
            }
        }
    }

    public function announcements(Request $request): View
    {
        StoreAccess::requirePermission($request->user(), 'platform.announcements.manage');

        return view('admin.announcements', ['announcements' => Announcement::latest()->orderByDesc('id')->paginate(RecordPage::size($request))->withQueryString(), 'stores' => Store::orderBy('display_name')->get()]);
    }

    public function announcement(Request $request): RedirectResponse
    {
        StoreAccess::requirePermission($request->user(), 'platform.announcements.manage');
        $data = $request->validate(['title' => ['required', 'string', 'max:255'], 'body' => ['required', 'string', 'max:10000'], 'email_owners' => ['nullable', 'boolean'], 'audience' => ['required', Rule::in(['all', 'selected'])], 'store_ids' => ['required_if:audience,selected', 'array', 'max:200'], 'store_ids.*' => ['ulid', 'exists:stores,id'], 'publish_at' => ['nullable', 'date_format:Y-m-d\TH:i'], 'expires_at' => ['nullable', 'date_format:Y-m-d\TH:i', 'after:publish_at']]);
        DB::transaction(function () use ($data, $request): void {
            $announcement = Announcement::create(['email_owners' => $request->boolean('email_owners'), 'title' => $data['title'], 'body' => $data['body'], 'user_id' => $request->user()->id, 'store_ids' => $data['audience'] === 'all' ? null : $data['store_ids'], 'publish_at' => ! empty($data['publish_at']) ? CarbonImmutable::parse($data['publish_at'], 'Asia/Manila')->utc() : null, 'expires_at' => ! empty($data['expires_at']) ? CarbonImmutable::parse($data['expires_at'], 'Asia/Manila')->utc() : null]);
            StoreAccess::audit($request->user(), null, 'announcement.created', $announcement->id);
        });

        return back()->with('status', 'Announcement saved. A blank publication date keeps it in draft.');
    }

    public function announcementStatus(Request $request, string $announcement): RedirectResponse
    {
        StoreAccess::requirePermission($request->user(), 'platform.announcements.manage');
        $data = $request->validate(['action' => ['required', Rule::in(['publish', 'withdraw'])]]);
        DB::transaction(function () use ($data, $announcement, $request): void {
            $item = Announcement::whereKey($announcement)->lockForUpdate()->firstOrFail();
            if ($data['action'] === 'publish') {
                StoreAccess::check(! $item->expires_at || $item->expires_at->isFuture(), 'An expired announcement cannot be published.');
                $item->update(['publish_at' => now(), 'withdrawn_at' => null]);
            } else {
                $item->update(['withdrawn_at' => now()]);
            }
            StoreAccess::audit($request->user(), null, 'announcement.'.$data['action'], $item->id);
        });

        return back()->with('status', 'Announcement updated.');
    }

    public function audit(Request $request): View
    {
        StoreAccess::requirePermission($request->user(), 'platform.audit.read');

        return view('admin.audit', ['events' => AuditEvent::latest('created_at')->orderByDesc('id')->paginate(RecordPage::size($request))->withQueryString()]);
    }
}
