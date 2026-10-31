<x-layouts::app :title="__('Admin Portal')">
    <div class="mx-auto w-full max-w-4xl space-y-6">
        <div>
            <flux:heading size="xl" level="1">{{ __('Admin Portal') }}</flux:heading>
            <flux:text class="mt-2">{{ __('Manage store access and review your platform.') }}</flux:text>
        </div>
        <div class="rounded-xl border border-dashed border-zinc-300 p-8 dark:border-zinc-700">
            <flux:heading>{{ __('Your platform workspace') }}</flux:heading>
            <flux:text class="mt-2">{{ __('Store registration, feature access, and announcements will be available as the next modules are added.') }}</flux:text>
        </div>
    </div>
</x-layouts::app>
