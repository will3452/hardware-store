<x-layouts::app :title="__('Workspace')">
    <div class="mx-auto w-full max-w-4xl space-y-6">
        <div>
            <flux:heading size="xl" level="1">{{ __('Welcome back, :name', ['name' => auth()->user()->name]) }}</flux:heading>
            <flux:text class="mt-2">{{ __('Open your workspace to continue.') }}</flux:text>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            @if (auth()->user()->hasPlatformPermission('platform.portal.access'))
                <a href="{{ route('admin.dashboard') }}" class="pyesio-launch-card" wire:navigate>
                    <span class="pyesio-launch-icon"><flux:icon.shield-check class="size-6" /></span><flux:heading>{{ __('Admin Portal') }}</flux:heading>
                    <flux:text class="mt-2">{{ __('Store administration and platform oversight.') }}</flux:text>
                <span class="pyesio-launch-arrow">Open workspace <flux:icon.arrow-right class="size-4" /></span></a>
            @endif
            <a href="{{ route('store.index') }}" class="pyesio-launch-card" wire:navigate>
                <span class="pyesio-launch-icon"><flux:icon.building-storefront class="size-6" /></span><flux:heading>{{ __('Store Portal') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Your hardware store workspace.') }}</flux:text>
            <span class="pyesio-launch-arrow">Open workspace <flux:icon.arrow-right class="size-4" /></span></a>
        </div>
    </div>
</x-layouts::app>
