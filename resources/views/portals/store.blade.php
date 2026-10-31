<x-layouts::app :title="$store->display_name">
    <div class="mx-auto w-full max-w-4xl space-y-6">
        <flux:link :href="route('store.index')" wire:navigate>&larr; {{ __('Your stores') }}</flux:link>
        <div>
            <flux:heading size="xl" level="1">{{ $store->display_name }}</flux:heading>
            <flux:text class="mt-2">{{ __('Store Portal') }} &middot; {{ $store->code }}</flux:text>
        </div>
        <div class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
            <flux:heading>{{ __('Store details') }}</flux:heading>
            <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                <div><dt class="text-sm text-zinc-500">{{ __('Address') }}</dt><dd class="mt-1">{{ $store->address_line_1 }}, {{ $store->city }}</dd></div>
                <div><dt class="text-sm text-zinc-500">{{ __('Currency and timezone') }}</dt><dd class="mt-1">{{ $store->currency }} &middot; {{ $store->timezone }}</dd></div>
            </dl>
        </div>
    </div>
</x-layouts::app>
