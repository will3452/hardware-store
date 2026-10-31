<x-layouts::app :title="__('Workspace')">
    <div class="mx-auto w-full max-w-4xl space-y-6">
        <div>
            <flux:heading size="xl" level="1">{{ __('Welcome back, :name', ['name' => auth()->user()->name]) }}</flux:heading>
            <flux:text class="mt-2">{{ __('Open your workspace to continue.') }}</flux:text>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            @if (auth()->user()->hasPlatformPermission('platform.portal.access'))
                <a href="{{ route('admin.dashboard') }}" class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700" wire:navigate>
                    <flux:heading>{{ __('Admin Portal') }}</flux:heading>
                    <flux:text class="mt-2">{{ __('Store administration and platform oversight.') }}</flux:text>
                </a>
            @endif
            <a href="{{ route('store.index') }}" class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700" wire:navigate>
                <flux:heading>{{ __('Store Portal') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Your hardware store workspace.') }}</flux:text>
            </a>
        </div>
    </div>
</x-layouts::app>
