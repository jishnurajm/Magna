<div class="fi-section space-y-4">
    @if (session('account_centre_status'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-300">
            {{ session('account_centre_status') }}
        </div>
    @endif
    @if (session('account_centre_error'))
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-500/20 dark:bg-red-500/10 dark:text-red-300">
            {{ session('account_centre_error') }}
        </div>
    @endif

    @if ($connected)
        <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-white/10 dark:bg-white/5">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $accountName }}</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $accountEmail }}</p>
                    @if ($connectedAt)
                        <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Connected {{ \Illuminate\Support\Carbon::parse($connectedAt)->diffForHumans() }}</p>
                    @endif
                </div>
                <form method="POST" action="{{ route('account-centre.disconnect') }}">
                    @csrf
                    <button type="submit" class="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-50 dark:border-red-500/30 dark:text-red-400 dark:hover:bg-red-500/10">
                        Disconnect
                    </button>
                </form>
            </div>
        </div>

        <div>
            <p class="mb-2 text-xs font-bold uppercase tracking-wider text-gray-400">Other Magna CMS installs on this account</p>
            @if (count($otherSites) === 0)
                <p class="text-sm text-gray-500 dark:text-gray-400">No other sites are connected to this account yet.</p>
            @else
                <ul class="divide-y divide-gray-100 rounded-xl border border-gray-200 dark:divide-white/5 dark:border-white/10">
                    @foreach ($otherSites as $site)
                        <li class="flex items-center justify-between px-4 py-3 text-sm">
                            <span class="font-medium text-gray-800 dark:text-gray-100">{{ $site['site_label'] ?? $site['site_url'] }}</span>
                            <span class="text-xs text-gray-400">{{ $site['last_seen_at'] ? \Illuminate\Support\Carbon::parse($site['last_seen_at'])->diffForHumans() : 'never seen' }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    @else
        <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-white/10 dark:bg-white/5">
            <p class="text-sm text-gray-600 dark:text-gray-300">
                Connect with your Magna Account to access the Magna ecosystem, manage your services,
                and enjoy a seamless experience across all Magna products.
            </p>
            <div class="mt-4 flex flex-wrap gap-2">
                <a href="{{ route('account-centre.connect', 'google') }}" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/5">
                    <svg class="h-4 w-4" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.76h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.76c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84A11 11 0 0 0 12 23z"/><path fill="#FBBC05" d="M5.84 14.09a6.6 6.6 0 0 1 0-4.18V7.07H2.18a11 11 0 0 0 0 9.86l3.66-2.84z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84C6.71 7.31 9.14 5.38 12 5.38z"/></svg>
                    Connect with Google
                </a>
                <a href="{{ route('account-centre.connect', 'github') }}" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/5">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M12 .5C5.7.5.5 5.7.5 12c0 5.1 3.3 9.4 7.9 10.9.6.1.8-.3.8-.6v-2c-3.2.7-3.9-1.5-3.9-1.5-.5-1.3-1.3-1.7-1.3-1.7-1.1-.7.1-.7.1-.7 1.2.1 1.8 1.2 1.8 1.2 1 1.8 2.7 1.3 3.4 1 .1-.8.4-1.3.7-1.6-2.6-.3-5.3-1.3-5.3-5.8 0-1.3.5-2.3 1.2-3.1-.1-.3-.5-1.5.1-3.1 0 0 1-.3 3.3 1.2a11.4 11.4 0 0 1 6 0C17 4.6 18 4.9 18 4.9c.6 1.6.2 2.8.1 3.1.8.8 1.2 1.8 1.2 3.1 0 4.5-2.7 5.5-5.3 5.8.4.4.8 1.1.8 2.3v3.3c0 .3.2.7.8.6 4.6-1.5 7.9-5.8 7.9-10.9C23.5 5.7 18.3.5 12 .5Z"/></svg>
                    Connect with GitHub
                </a>
            </div>
        </div>
    @endif
</div>
