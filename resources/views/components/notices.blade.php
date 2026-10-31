@if (session('status'))<div role="status" class="hms-notice">{{ session('status') }}</div>@endif
@if ($errors->any())<div role="alert" class="hms-error"><strong>Please review the following:</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@if (session('invitation_link'))<div class="hms-card space-y-3"><h2>Owner activation link</h2><p>This link expires in 72 hours. Share it privately with the intended owner. It is shown only after creating or resending an invitation.</p><input aria-label="Activation link" readonly class="w-full" value="{{ session('invitation_link') }}" onclick="this.select()"></div>@endif

@if(session('recovery_link'))<div class="hms-card space-y-3"><h2>Owner recovery link</h2><input aria-label="Owner recovery link" readonly value="{{ session('recovery_link') }}" onclick="this.select()"></div>@endif
