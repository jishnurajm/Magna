<x-filament-panels::page>

    {{-- ─── One-time credentials reveal ──────────────────────────────────── --}}
    @if ($generatedKey)
    <div
        x-data="{
            copied: null,
            apiKey: @js($generatedKey),
            apiSecret: @js($generatedSecret),
            async copy(which) {
                const text = which === 'key' ? this.apiKey : this.apiSecret;
                try {
                    await navigator.clipboard.writeText(text);
                } catch {
                    /* fallback for http / older browsers */
                    const el = document.createElement('textarea');
                    el.value = text;
                    el.style.cssText = 'position:fixed;opacity:0';
                    document.body.appendChild(el);
                    el.focus();
                    el.select();
                    document.execCommand('copy');
                    el.remove();
                }
                this.copied = which;
                setTimeout(() => { this.copied = null; }, 2500);
            }
        }"
        class="mb-6 rounded-xl border border-gray-700 bg-gray-900 shadow-lg overflow-hidden"
    >
        {{-- Header --------------------------------------------------------- --}}
        <div class="flex items-center justify-between gap-4 px-5 py-4 bg-gray-800 border-b border-gray-700">
            <div class="flex items-center gap-3">
                <div class="flex-shrink-0 w-9 h-9 rounded-full bg-success-500/20 flex items-center justify-center">
                    <svg class="w-5 h-5 text-success-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/>
                    </svg>
                </div>
                <div>
                    <p class="text-sm font-semibold text-gray-100">API key generated — copy the credentials now</p>
                    <p class="text-xs text-gray-400 mt-0.5">The secret is shown <strong class="text-gray-300">only once</strong> and cannot be retrieved after you dismiss this.</p>
                </div>
            </div>
            <button wire:click="clearGenerated" class="flex-shrink-0 w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:text-gray-100 hover:bg-gray-700 transition-colors" title="Dismiss">
                <svg class="w-5 h-5" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg>
            </button>
        </div>

        {{-- Credentials ---------------------------------------------------- --}}
        <div class="px-5 py-5 space-y-4">

            {{-- Key -------------------------------------------------------- --}}
            <div>
                <div class="flex items-baseline justify-between mb-1.5">
                    <label class="text-xs font-semibold uppercase tracking-wider text-gray-400">API Key</label>
                    <span class="text-xs text-gray-500 font-mono">X-Magna-Key</span>
                </div>
                <div class="flex items-stretch rounded-lg border border-gray-700 overflow-hidden">
                    <code
                        class="flex-1 px-4 py-2.5 font-mono text-sm bg-gray-800 text-gray-100 break-all select-all"
                        x-text="apiKey"
                    ></code>
                    <button
                        x-on:click="copy('key')"
                        class="flex-shrink-0 flex items-center gap-2 px-4 border-l border-gray-700 text-sm font-medium min-w-[90px] justify-center transition-colors"
                        :class="copied === 'key'
                            ? 'bg-success-950/30 text-success-400'
                            : 'bg-gray-900 text-gray-400 hover:bg-gray-800 hover:text-gray-200'"
                    >
                        <svg x-show="copied !== 'key'" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path d="M7 3.5A1.5 1.5 0 018.5 2h3.879a1.5 1.5 0 011.06.44l3.122 3.12A1.5 1.5 0 0117 6.622V12.5a1.5 1.5 0 01-1.5 1.5h-1v-3.379a3 3 0 00-.879-2.121L10.5 5.379A3 3 0 008.379 4.5H7v-1z"/><path d="M4.5 6A1.5 1.5 0 003 7.5v9A1.5 1.5 0 004.5 18h7a1.5 1.5 0 001.5-1.5v-5.879a1.5 1.5 0 00-.44-1.06L9.44 6.439A1.5 1.5 0 008.378 6H4.5z"/></svg>
                        <svg x-show="copied === 'key'" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg>
                        <span x-text="copied === 'key' ? 'Copied!' : 'Copy'"></span>
                    </button>
                </div>
            </div>

            {{-- Secret ----------------------------------------------------- --}}
            <div>
                <div class="flex items-baseline justify-between mb-1.5">
                    <label class="text-xs font-semibold uppercase tracking-wider text-gray-400">
                        Secret
                        <span class="ml-1.5 text-[10px] font-medium text-gray-500 bg-gray-800 border border-gray-700 rounded px-1.5 py-0.5">shown once</span>
                    </label>
                    <span class="text-xs text-gray-500 font-mono">X-Magna-Secret</span>
                </div>
                <div class="flex items-stretch rounded-lg border border-gray-700 overflow-hidden">
                    <code
                        class="flex-1 px-4 py-2.5 font-mono text-sm bg-gray-800 text-gray-100 break-all select-all"
                        x-text="apiSecret"
                    ></code>
                    <button
                        x-on:click="copy('secret')"
                        class="flex-shrink-0 flex items-center gap-2 px-4 border-l border-gray-700 text-sm font-medium min-w-[90px] justify-center transition-colors"
                        :class="copied === 'secret'
                            ? 'bg-success-950/30 text-success-400'
                            : 'bg-gray-900 text-gray-400 hover:bg-gray-800 hover:text-gray-200'"
                    >
                        <svg x-show="copied !== 'secret'" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path d="M7 3.5A1.5 1.5 0 018.5 2h3.879a1.5 1.5 0 011.06.44l3.122 3.12A1.5 1.5 0 0117 6.622V12.5a1.5 1.5 0 01-1.5 1.5h-1v-3.379a3 3 0 00-.879-2.121L10.5 5.379A3 3 0 008.379 4.5H7v-1z"/><path d="M4.5 6A1.5 1.5 0 003 7.5v9A1.5 1.5 0 004.5 18h7a1.5 1.5 0 001.5-1.5v-5.879a1.5 1.5 0 00-.44-1.06L9.44 6.439A1.5 1.5 0 008.378 6H4.5z"/></svg>
                        <svg x-show="copied === 'secret'" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg>
                        <span x-text="copied === 'secret' ? 'Copied!' : 'Copy'"></span>
                    </button>
                </div>
            </div>

            {{-- Usage snippet ---------------------------------------------- --}}
            <div class="rounded-lg bg-gray-950 px-4 py-3 space-y-1.5">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">HTTP headers for your app</p>
                <pre class="text-xs font-mono leading-5"><span class="text-gray-500">X-Magna-Key:</span>    <span class="text-green-400" x-text="apiKey"></span>
<span class="text-gray-500">X-Magna-Secret:</span> <span class="text-yellow-400" x-text="apiSecret"></span></pre>
            </div>
        </div>

        {{-- Footer --------------------------------------------------------- --}}
        <div class="flex items-center justify-between gap-4 px-5 py-3.5 bg-gray-800 border-t border-gray-700">
            <p class="text-xs text-gray-500">
                Store in environment variables or a secrets manager. Never commit to source control.
            </p>
            <button
                wire:click="clearGenerated"
                class="flex-shrink-0 px-4 py-2 rounded-lg bg-gray-700 hover:bg-gray-600 text-gray-100 text-sm font-medium transition-colors"
            >
                I've saved these — dismiss
            </button>
        </div>
    </div>
    @endif

    {{-- ─── Keys table ─────────────────────────────────────────────────── --}}
    {{ $this->table }}

</x-filament-panels::page>
