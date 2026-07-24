@php
    // Full class strings per category (Tailwind can't see interpolated names).
    $config = [
        'images' => [
            'icon' => 'heroicon-o-photo',
            'bar' => 'bg-emerald-500',
            'iconWrap' => 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
            'badge' => 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
            'line' => 'bg-emerald-500',
            'hover' => 'hover:bg-emerald-50/50 dark:hover:bg-emerald-950/20',
            'active' => 'ring-2 ring-emerald-500/60 bg-emerald-50/60 dark:bg-emerald-950/20',
        ],
        'pdf' => [
            'icon' => 'heroicon-o-document-text',
            'bar' => 'bg-violet-500',
            'iconWrap' => 'bg-violet-500/10 text-violet-600 dark:text-violet-400',
            'badge' => 'bg-violet-500/10 text-violet-600 dark:text-violet-400',
            'line' => 'bg-violet-500',
            'hover' => 'hover:bg-violet-50/50 dark:hover:bg-violet-950/20',
            'active' => 'ring-2 ring-violet-500/60 bg-violet-50/60 dark:bg-violet-950/20',
        ],
        'video' => [
            'icon' => 'heroicon-o-film',
            'bar' => 'bg-blue-500',
            'iconWrap' => 'bg-blue-500/10 text-blue-600 dark:text-blue-400',
            'badge' => 'bg-blue-500/10 text-blue-600 dark:text-blue-400',
            'line' => 'bg-blue-500',
            'hover' => 'hover:bg-blue-50/50 dark:hover:bg-blue-950/20',
            'active' => 'ring-2 ring-blue-500/60 bg-blue-50/60 dark:bg-blue-950/20',
        ],
        'others' => [
            'icon' => 'heroicon-o-folder-open',
            'bar' => 'bg-amber-500',
            'iconWrap' => 'bg-amber-500/10 text-amber-600 dark:text-amber-500',
            'badge' => 'bg-amber-500/10 text-amber-600 dark:text-amber-500',
            'line' => 'bg-amber-500',
            'hover' => 'hover:bg-amber-50/50 dark:hover:bg-amber-950/20',
            'active' => 'ring-2 ring-amber-500/60 bg-amber-50/60 dark:bg-amber-950/20',
        ],
    ];
@endphp

<div class="w-full bg-white dark:bg-slate-800 rounded-3xl shadow-sm border border-slate-100 dark:border-slate-700/50 p-6 md:p-8">

    {{-- Live counters --}}
    <div class="grid grid-cols-1 {{ $hasDisk ? 'md:grid-cols-3' : 'md:grid-cols-2' }} gap-4 mb-6">
        <div class="bg-slate-50 dark:bg-slate-900/50 border border-slate-100 dark:border-slate-700/30 rounded-2xl p-4">
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block mb-1">Total Space Used</span>
            <div class="flex items-baseline gap-2">
                <span class="text-2xl font-bold text-slate-900 dark:text-white">{{ $totalUsedText }}</span>
                @if ($hasDisk)
                    <span class="text-xs text-slate-500">of {{ $capacityText }} disk</span>
                @endif
            </div>
        </div>

        <div class="bg-slate-50 dark:bg-slate-900/50 border border-slate-100 dark:border-slate-700/30 rounded-2xl p-4">
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block mb-1">Total Files Count</span>
            <div class="flex items-baseline gap-2">
                <span class="text-2xl font-bold text-slate-900 dark:text-white">{{ number_format($totalCount) }}</span>
                <span class="text-xs text-slate-500">media assets</span>
            </div>
        </div>

        @if ($hasDisk)
            <div class="bg-slate-50 dark:bg-slate-900/50 border border-slate-100 dark:border-slate-700/30 rounded-2xl p-4">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block mb-1">Available Space</span>
                <div class="flex items-baseline gap-2">
                    <span class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ $availableText }}</span>
                    <span class="text-xs text-slate-500">free on disk</span>
                </div>
            </div>
        @endif
    </div>

    {{-- Storage distribution bar --}}
    <div class="mb-8">
        <div class="flex items-center justify-between mb-3 text-sm">
            <span class="font-medium text-slate-700 dark:text-slate-300">Storage Distribution</span>
            @if ($hasDisk)
                <span class="font-semibold text-indigo-600 dark:text-indigo-400">{{ $utilizationPct }}% Full</span>
            @else
                <span class="font-semibold text-indigo-600 dark:text-indigo-400">{{ $totalUsedText }} used</span>
            @endif
        </div>

        <div class="relative w-full h-7 bg-slate-100 dark:bg-slate-700 rounded-2xl overflow-hidden flex shadow-inner border border-slate-200/40 dark:border-slate-600/40">
            @foreach ($categories as $key => $cat)
                <div class="h-full {{ $config[$key]['bar'] }} transition-all duration-500 ease-out relative"
                     style="width: {{ $cat['pct'] }}%;"
                     title="{{ $cat['label'] }}: {{ $cat['pct'] }}%">
                    <div class="absolute inset-0 bg-gradient-to-t from-black/10 to-transparent"></div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Category cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        @foreach ($categories as $key => $cat)
            <button
                type="button"
                wire:click="selectCategory('{{ $key }}')"
                class="group relative text-left border border-slate-100 dark:border-slate-800 rounded-2xl p-4 transition-all duration-300 hover:scale-[1.02] cursor-pointer
                    {{ $activeCategory === $key
                        ? $config[$key]['active']
                        : 'bg-slate-50 dark:bg-slate-900/40 '.$config[$key]['hover'] }}"
            >
                <div class="flex items-center justify-between mb-3">
                    <div class="p-2.5 rounded-xl {{ $config[$key]['iconWrap'] }}">
                        @svg($config[$key]['icon'], 'w-5 h-5 block')
                    </div>
                    <span class="text-xs font-bold px-2 py-0.5 rounded-full {{ $config[$key]['badge'] }}">{{ $cat['pct'] }}%</span>
                </div>
                <h3 class="font-semibold text-slate-800 dark:text-slate-200">{{ $cat['label'] }}</h3>
                <div class="mt-2 flex items-baseline justify-between">
                    <span class="text-xs text-slate-500 dark:text-slate-400">{{ number_format($cat['count']) }} {{ Str::plural('file', $cat['count']) }}</span>
                    <span class="text-base font-bold text-slate-800 dark:text-slate-100">{{ $cat['sizeText'] }}</span>
                </div>
                <div class="absolute bottom-0 left-4 right-4 h-1 {{ $config[$key]['line'] }} rounded-t-full transform {{ $activeCategory === $key ? 'scale-x-100' : 'scale-x-0 group-hover:scale-x-100' }} transition-transform duration-300"></div>
            </button>
        @endforeach
    </div>

    @if ($activeCategory)
        <div class="mt-4 flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400">
            <span>Showing only <strong class="text-slate-700 dark:text-slate-200">{{ $categories[$activeCategory]['label'] }}</strong> below.</span>
            <button type="button" wire:click="selectCategory('{{ $activeCategory }}')" class="text-indigo-600 dark:text-indigo-400 hover:underline font-medium">Clear filter</button>
        </div>
    @endif
</div>
