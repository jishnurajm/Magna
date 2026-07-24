<x-filament-panels::page>
    {{ $this->form }}

    @php /** @var \Magna\Users\User $authUser */ $authUser = auth()->user(); @endphp

    <x-filament::section>
        <x-slot name="heading">Two-factor authentication</x-slot>
        <x-slot name="description">Add an extra layer of security to your account.</x-slot>

        <div class="flex items-center justify-between gap-4">
            <div>
                @if ($authUser->hasTwoFactorEnabled())
                    <div class="flex items-center gap-2 text-success-600 dark:text-success-400">
                        <x-heroicon-s-shield-check class="w-5 h-5"/>
                        <span class="text-sm font-medium">2FA is enabled on your account.</span>
                    </div>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Your account is protected with a time-based one-time password (TOTP).
                    </p>
                @else
                    <div class="flex items-center gap-2 text-warning-600 dark:text-warning-400">
                        <x-heroicon-s-shield-exclamation class="w-5 h-5"/>
                        <span class="text-sm font-medium">2FA is not enabled.</span>
                    </div>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Enable two-factor authentication to secure your account.
                    </p>
                @endif
            </div>

            @if (! $authUser->hasTwoFactorEnabled())
                <a
                    href="{{ route('auth.two-factor.setup') }}"
                    class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-primary-600 text-white hover:bg-primary-700 transition-colors"
                >
                    <x-heroicon-o-shield-check class="w-4 h-4"/>
                    Enable 2FA
                </a>
            @endif
        </div>
    </x-filament::section>
</x-filament-panels::page>
