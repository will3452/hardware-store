<x-admin-screen title="System status">
<p>Operational checks · updated {{ now()->timezone('Asia/Manila')->format('M j, Y H:i:s') }} Asia/Manila</p>
@foreach($checks as $check)<section class="hms-card space-y-2"><div class="hms-top"><h2>{{ $check['label'] }}</h2><span class="hms-badge">{{ $check['ready'] ? 'OK' : 'Needs attention' }}</span></div><p>{{ $check['detail'] }}</p></section>@endforeach
<div class="hms-card space-y-3"><h2>Recovery and invoicing</h2><p>Keep an independent protected copy of recovery snapshots and rehearse restoration. Checksums verify the saved files; they do not prove that a recovery has been completed.</p><p>Sales printouts remain internal records. Continue the store’s registered invoicing process until its tax and invoice setup is approved.</p></div>
</x-admin-screen>
