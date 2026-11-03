<x-admin-screen title="Email delivery">
<div class="hms-card space-y-4">
    <div class="hms-top"><div><h2>{{ $configured ? 'SMTP configured' : 'Email is not configured' }}</h2><p>Messages normally leave within a few minutes. “Sent” means accepted by the mail server; check the recipient’s inbox or spam folder to confirm arrival.</p></div><span class="hms-badge">{{ $counts['pending'] ?? 0 }} queued · {{ $counts['failed'] ?? 0 }} failed</span></div>
    <p>Last mail-server acceptance: {{ $lastSent?->timezone('Asia/Manila')->format('M j, Y H:i:s') ?? 'No accepted messages yet' }}</p>
    <form method="POST" action="{{ route('admin.email.test') }}">@csrf<button class="hms-secondary" @disabled(!$configured)>Send test to my email</button></form>
</div>
<form method="GET" class="hms-card hms-grid">
    <label class="hms-field"><span>Delivery status</span><select name="status"><option value="">All statuses</option>@foreach(['pending', 'sending', 'sent', 'failed', 'cancelled'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>@endforeach</select></label>
    @if(request('store_id'))<input type="hidden" name="store_id" value="{{ request('store_id') }}">@endif
    <div class="flex items-end gap-3"><button class="hms-primary">Filter</button><a class="hms-secondary" href="{{ route('admin.email') }}">Show all</a></div>
</form>
<div class="hms-table-wrap"><table><thead><tr><th>Created · Manila</th><th>Purpose / recipient</th><th>Status</th><th>Attempts</th><th>Detail</th><th>Action</th></tr></thead><tbody>
@forelse($deliveries as $delivery)
<tr><td>{{ $delivery->created_at->timezone('Asia/Manila')->format('M j, H:i') }}</td><td>{{ ucfirst(str_replace('_', ' ', $delivery->kind)) }}<br>{{ $delivery->recipient }}</td><td><span class="hms-badge">{{ ucfirst($delivery->status) }}</span></td><td>{{ $delivery->attempts }}</td><td>
@if($delivery->sent_at)Accepted {{ $delivery->sent_at->timezone('Asia/Manila')->format('M j, H:i') }}
@elseif($delivery->last_error === 'smtp_delivery_failed')Mail server did not confirm acceptance. Check SMTP credentials, connection, and sending limits.
@elseif($delivery->last_error === 'message_processing_failed')Message could not be processed. Contact the operator.
@elseif($delivery->last_error)Link expired or recipient access changed.
@elseif($delivery->status === 'pending')Waiting for the scheduler.
@endif
</td><td>@if($delivery->status === 'failed' && $delivery->expires_at->isFuture())<form method="POST" action="{{ route('admin.email.retry', $delivery->id) }}">@csrf<button class="hms-secondary">Retry delivery</button></form>@endif</td></tr>
@empty<tr><td colspan="6">No email deliveries match this filter.</td></tr>@endforelse
</tbody></table></div>{{ $deliveries->links() }}
<p class="hms-help">Delivery retries reuse the original link. To replace an expired or revoked invitation, create a new invitation from store management. Withdrawing an announcement stops unsent emails; accepted emails cannot be recalled.</p>
</x-admin-screen>
