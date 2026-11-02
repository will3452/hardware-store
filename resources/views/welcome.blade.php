<x-layouts::auth :title="__('Welcome')">
    <div class="space-y-6 text-center">
        <x-auth-header :title="__('Welcome to Pyesio')" :description="__('Your store. One connected workspace.')" />
        <flux:text>{{ __('Sign in with the account created for your store.') }}</flux:text>
        <flux:button variant="primary" :href="auth()->check() ? route('dashboard') : route('login')" class="w-full" wire:navigate>
            {{ auth()->check() ? __('Open workspace') : __('Sign in') }}
        </flux:button>
    </div>
</x-layouts::auth>
