@props(['progress', 'store', 'readOnly' => false])
<details id="setup-checklist" class="hms-card hms-onboarding no-print" @if($progress['expanded']) open @endif>
    <summary class="hms-onboarding-summary">
        <span><span class="hms-eyebrow">GETTING STARTED</span><span class="hms-onboarding-title">{{ $progress['complete'] ? 'Setup milestones complete' : 'Set up your store' }}</span><span class="hms-help block">{{ $progress['completed'] }} of {{ $progress['total'] }} steps complete · {{ $progress['complete'] ? 'Review checklist' : 'Progress saved automatically' }}</span></span>
        <span class="hms-onboarding-summary-end"><span class="hms-badge">{{ $progress['percent'] }}%</span><span class="hms-onboarding-toggle" aria-hidden="true">⌄</span></span>
    </summary>
    <div class="hms-onboarding-body">
        <progress class="hms-onboarding-progress" value="{{ $progress['completed'] }}" max="{{ $progress['total'] }}" aria-label="Store setup progress">{{ $progress['percent'] }}%</progress>
        <p class="hms-help">{{ $readOnly ? 'Progress comes from this store’s saved records. Only the owner can complete these steps.' : 'Follow these steps at your own pace. Each step updates when you save the relevant record.' }} Only enabled features appear here.</p>
        @if($progress['next'])
        @php($next = $progress['next'])
        <div class="hms-onboarding-next"><div><span class="hms-eyebrow">{{ $readOnly ? 'NEXT FOR THE OWNER' : 'NEXT STEP' }}</span><h3>{{ $next['title'] }}</h3><p>{{ $next['description'] }}</p></div><a class="{{ $readOnly ? 'hms-secondary' : 'hms-primary' }}" href="{{ \App\Support\StoreWorkspace::route('store.page', [$store->id, $next['page']]).($next['anchor'] ? '#'.$next['anchor'] : '') }}">{{ $readOnly ? 'View details' : $next['action'] }} →</a></div>
        @else
        <p>All setup milestones for your enabled features are complete. Keep your catalog and stock up to date as you operate.</p>
        @endif
        <ol class="hms-onboarding-steps">
            @foreach($progress['steps'] as $step)
            <li @class(['is-complete' => $step['done']])>
                <span class="hms-onboarding-marker" aria-hidden="true">{{ $step['done'] ? '✓' : $loop->iteration }}</span>
                <div><a href="{{ \App\Support\StoreWorkspace::route('store.page', [$store->id, $step['page']]).($step['anchor'] ? '#'.$step['anchor'] : '') }}">{{ $step['title'] }}</a><span class="hms-help block">{{ $step['done'] ? 'Done' : 'To do' }}</span><p class="hms-help">{{ $step['description'] }}</p></div>
            </li>
            @endforeach
        </ol>
        <p class="hms-help">These are first-use milestones, not daily tasks. Closing the drawer or selling out of stock does not reset them. Use your real business records; the checklist does not block other work.</p>
    </div>
</details>
