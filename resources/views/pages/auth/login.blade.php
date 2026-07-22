<x-layouts::auth :title="__('Log in')">
    <div
        x-data="{
            fill(email) {
                const emailInput = $el.querySelector('input[name=email]');
                const passwordInput = $el.querySelector('input[name=password]');
                emailInput.value = email;
                passwordInput.value = 'password';
                emailInput.dispatchEvent(new Event('input'));
                passwordInput.dispatchEvent(new Event('input'));
            },
        }"
        class="flex flex-col gap-6"
    >
        <x-auth-header :title="__('Log in to your account')" :description="__('Enter your email and password below to log in')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <!-- Demo access -->
        <flux:callout variant="secondary" icon="key" :heading="__('Demo access')">
            <p>
                {{ __('Seeded demo — the password for both accounts is') }}
                <span class="rounded bg-zinc-200/70 px-1 font-mono text-xs dark:bg-zinc-700">password</span>.
                {{ __('The reviewer account is prefilled below, so you can just press Log in.') }}
            </p>

            <x-slot name="actions" class="flex-wrap">
                <flux:button size="xs" type="button" icon="user" x-on:click="fill('demo@example.com')">
                    demo@example.com
                </flux:button>
                <flux:button size="xs" variant="ghost" type="button" icon="users" x-on:click="fill('colleague@example.com')">
                    colleague@example.com
                </flux:button>
            </x-slot>
        </flux:callout>

        <x-passkey-verify />

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-6">
            @csrf

            <!-- Email Address -->
            <flux:input
                name="email"
                :label="__('Email address')"
                :value="old('email', 'demo@example.com')"
                type="email"
                required
                autofocus
                autocomplete="email"
                placeholder="email@example.com"
            />

            <!-- Password -->
            <div class="relative">
                <flux:input
                    name="password"
                    :label="__('Password')"
                    type="password"
                    :value="old('password', 'password')"
                    required
                    autocomplete="current-password"
                    :placeholder="__('Password')"
                    viewable
                />

                @if (Route::has('password.request'))
                    <flux:link class="absolute top-0 text-sm end-0" :href="route('password.request')" wire:navigate>
                        {{ __('Forgot your password?') }}
                    </flux:link>
                @endif
            </div>

            <!-- Remember Me -->
            <flux:checkbox name="remember" :label="__('Remember me')" :checked="old('remember')" />

            <div class="flex items-center justify-end">
                <flux:button variant="primary" type="submit" class="w-full" data-test="login-button">
                    {{ __('Log in') }}
                </flux:button>
            </div>
        </form>

        <div class="space-x-1 text-sm text-center rtl:space-x-reverse text-zinc-600 dark:text-zinc-400">
            <span>{{ __('Don\'t have an account?') }}</span>
            <flux:link :href="route('register')" wire:navigate>{{ __('Sign up') }}</flux:link>
        </div>
    </div>
</x-layouts::auth>
