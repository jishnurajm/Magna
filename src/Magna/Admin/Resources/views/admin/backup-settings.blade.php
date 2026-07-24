<x-filament-panels::page>
    @if($running)
    @php $backupProgress = \Magna\Backup\Jobs\RunBackupJob::progress(); @endphp
    <div wire:poll.2s="pollBackupRun" class="mb-6 rounded-2xl border border-amber-500/20 bg-amber-500/5 p-5">
        <div class="flex items-center gap-3">
            <svg class="w-4 h-4 shrink-0 animate-spin text-amber-500" viewBox="0 0 24 24" fill="none">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
            </svg>
            <div class="min-w-0">
                <p class="text-sm font-bold text-amber-700 dark:text-amber-400">Backup running…</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $backupProgress['message'] ?: 'Starting…' }}</p>
            </div>
        </div>
    </div>
    @endif

    {{ $this->form }}
</x-filament-panels::page>
