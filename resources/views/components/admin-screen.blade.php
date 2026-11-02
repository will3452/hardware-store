@props(['title' => 'Admin Portal'])
<x-layouts::app :title="$title">
<div class="hms-workspace"><div class="hms-top"><div><p class="hms-eyebrow">PLATFORM WORKSPACE</p><h1>{{ $title }}</h1><p>Manage independent stores and see how each business is performing.</p></div></div>
<nav class="hms-nav" aria-label="Admin navigation"><a href="{{ route('admin.dashboard') }}">Stores & reports</a>@if(auth()->user()->hasPlatformPermission('platform.announcements.manage'))<a href="{{ route('admin.announcements') }}">Announcements</a>@endif @if(auth()->user()->hasPlatformPermission('platform.audit.read'))<a href="{{ route('admin.audit') }}">Audit trail</a>@endif</nav>
@if(auth()->user()->hasPlatformPermission('platform.stores.manage'))<a href="{{ route('admin.health') }}" class="text-sm underline">System status</a>@endif
<x-notices />{{ $slot }}</div>
</x-layouts::app>
