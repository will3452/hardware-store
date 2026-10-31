<x-layouts::app :title="__('Store Portal')">
    <div class="mx-auto w-full max-w-4xl space-y-6">
        <div>
            <flux:heading size="xl" level="1">{{ __('Store Portal') }}</flux:heading>
            <flux:text class="mt-2">{{ __('Choose a store to open its workspace.') }}</flux:text>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            @forelse ($memberships as $membership)
                <a href="{{ route('store.dashboard', $membership->store_id) }}" class="rounded-xl border border-zinc-200 p-6 transition hover:border-zinc-400 dark:border-zinc-700 dark:hover:border-zinc-500" wire:navigate>
                    <flux:heading>{{ $membership->store->display_name }}</flux:heading>
                    <flux:text class="mt-2">{{ $membership->store->city }}</flux:text>
                    <flux:text class="mt-4">{{ __('Open store') }} &rarr;</flux:text>
                </a>
            @empty
                <div class="rounded-xl border border-dashed border-zinc-300 p-8 sm:col-span-2 dark:border-zinc-700">
                    <flux:heading>{{ __('No active store access') }}</flux:heading>
                    <flux:text class="mt-2">{{ __('Your store will appear here after its owner account is activated.') }}</flux:text>
                </div>
            @endforelse
        </div>
    </div>
</x-layouts::app>
