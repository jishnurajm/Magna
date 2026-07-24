{{--
  Magna Block Editor — Section → Column → Block tree editor.
  Embedded in the Filament entry form as a Livewire component.
--}}

<div class="magna-block-editor" x-data="{ addBlockModal: false, addBlockTarget: null, cloudModal: false }">

    {{-- Header bar --}}
    <div class="flex items-center justify-between rounded-t-lg border border-gray-200 bg-gray-50 px-4 py-2.5 dark:border-white/10 dark:bg-white/5">
        <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">Block Editor</span>
        <div class="flex items-center gap-3">
            @if($saveStatus)
                <span class="text-xs text-gray-500 dark:text-gray-400">{{ $saveStatus }}</span>
            @endif
            {{-- Cloud Library hook (Stage 19 wires this) --}}
            <button type="button"
                    @click="cloudModal = true"
                    class="inline-flex items-center gap-1.5 rounded-md bg-indigo-50 px-3 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-100 dark:bg-indigo-950/50 dark:text-indigo-300 dark:hover:bg-indigo-900/60">
                <x-heroicon-o-cloud-arrow-down class="h-3.5 w-3.5"/>
                Browse Cloud Library
            </button>
            <button type="button"
                    wire:click="save"
                    class="inline-flex items-center gap-1.5 rounded-md bg-primary-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-primary-700">
                <x-heroicon-o-cloud-arrow-up class="h-3.5 w-3.5"/>
                Save
            </button>
        </div>
    </div>

    {{-- Section list --}}
    <div class="divide-y divide-gray-100 dark:divide-white/10">

        @forelse($sections as $si => $section)
            <div class="magna-block-editor__section group border border-t-0 border-gray-200 dark:border-white/10"
                 x-data="{ open: true, settingsOpen: false }">

                {{-- Section header --}}
                <div class="flex items-center gap-2 bg-white px-4 py-2 dark:bg-white/5">
                    <button type="button" @click="open = !open" class="flex-1 text-left">
                        <span class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            Section {{ $si + 1 }}
                            @if(!empty($section['settings']['anchor']))
                                <span class="text-gray-400">#{{ $section['settings']['anchor'] }}</span>
                            @endif
                        </span>
                    </button>

                    <div class="flex items-center gap-1 opacity-0 transition-opacity group-hover:opacity-100">
                        <button type="button" wire:click="moveSectionUp({{ $si }})" title="Move up" class="rounded p-1 hover:bg-gray-100 dark:hover:bg-white/10">
                            <x-heroicon-o-arrow-up class="h-3.5 w-3.5 text-gray-500"/>
                        </button>
                        <button type="button" wire:click="moveSectionDown({{ $si }})" title="Move down" class="rounded p-1 hover:bg-gray-100 dark:hover:bg-white/10">
                            <x-heroicon-o-arrow-down class="h-3.5 w-3.5 text-gray-500"/>
                        </button>
                        <button type="button" @click="settingsOpen = !settingsOpen" title="Section settings" class="rounded p-1 hover:bg-gray-100 dark:hover:bg-white/10">
                            <x-heroicon-o-cog-6-tooth class="h-3.5 w-3.5 text-gray-500"/>
                        </button>
                        <button type="button" wire:click="removeSection({{ $si }})" title="Delete section"
                                wire:confirm="Delete this section and all its blocks?"
                                class="rounded p-1 text-red-500 hover:bg-red-50 dark:hover:bg-red-950/30">
                            <x-heroicon-o-trash class="h-3.5 w-3.5"/>
                        </button>
                    </div>
                </div>

                {{-- Section settings panel --}}
                <div x-show="settingsOpen" x-collapse class="border-t border-gray-100 bg-gray-50 px-4 py-4 dark:border-white/10 dark:bg-white/5">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Background type</label>
                            <select wire:model.live="sections.{{ $si }}.settings.background.type"
                                    class="w-full rounded-md border border-gray-200 bg-white px-2.5 py-1.5 text-xs dark:border-white/10 dark:bg-white/10 dark:text-white">
                                <option value="none">None</option>
                                <option value="color">Solid colour</option>
                                <option value="gradient">Gradient</option>
                                <option value="image">Image</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Max width</label>
                            <select wire:model.live="sections.{{ $si }}.settings.maxWidth"
                                    class="w-full rounded-md border border-gray-200 bg-white px-2.5 py-1.5 text-xs dark:border-white/10 dark:bg-white/10 dark:text-white">
                                @foreach(['sm','md','lg','xl','2xl','full'] as $mw)
                                    <option value="{{ $mw }}">{{ strtoupper($mw) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Anchor ID</label>
                            <input type="text" wire:model.live="sections.{{ $si }}.settings.anchor"
                                   placeholder="e.g. hero"
                                   class="w-full rounded-md border border-gray-200 bg-white px-2.5 py-1.5 text-xs dark:border-white/10 dark:bg-white/10 dark:text-white">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">CSS class</label>
                            <input type="text" wire:model.live="sections.{{ $si }}.settings.cssClass"
                                   class="w-full rounded-md border border-gray-200 bg-white px-2.5 py-1.5 text-xs dark:border-white/10 dark:bg-white/10 dark:text-white">
                        </div>
                    </div>

                    {{-- Column layout picker --}}
                    <div class="mt-4">
                        <p class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-2">Column layout</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach([[12], [6,6], [4,8], [8,4], [4,4,4], [3,3,3,3], [3,6,3]] as $preset)
                                <button type="button"
                                        wire:click="applyColumnLayout({{ $si }}, {{ json_encode($preset) }})"
                                        class="rounded border border-gray-200 bg-white px-2 py-1 text-xs hover:bg-indigo-50 dark:border-white/10 dark:bg-white/5 dark:hover:bg-indigo-950/40"
                                        title="{{ implode('+', $preset) }}">
                                    [{{ implode('+', $preset) }}]
                                </button>
                            @endforeach
                        </div>
                    </div>

                    {{-- Token overrides --}}
                    <div class="mt-4" x-data="{}">
                        <div class="flex items-center justify-between mb-2">
                            <p class="text-xs font-medium text-gray-600 dark:text-gray-400">Token overrides</p>
                            <button type="button" wire:click="addTokenOverride({{ $si }})"
                                    class="text-xs text-indigo-600 hover:underline dark:text-indigo-400">+ Add</button>
                        </div>
                        <p class="mb-2 text-xs text-gray-400">Override design tokens for this section only (e.g. key: <code>color-on-surface</code>  value: <code>#ffffff</code>). Changes take effect immediately in the preview.</p>
                        @foreach($section['settings']['tokenOverrides'] ?? [] as $oi => $override)
                            <div class="flex items-center gap-2 mb-1">
                                <input type="text"
                                       wire:model.live="sections.{{ $si }}.settings.tokenOverrides.{{ $oi }}.key"
                                       placeholder="key (e.g. color-on-surface)"
                                       class="flex-1 rounded border border-gray-200 bg-white px-2 py-1 text-xs dark:border-white/10 dark:bg-white/10 dark:text-white">
                                <input type="text"
                                       wire:model.live="sections.{{ $si }}.settings.tokenOverrides.{{ $oi }}.value"
                                       placeholder="value (e.g. #ffffff)"
                                       class="flex-1 rounded border border-gray-200 bg-white px-2 py-1 text-xs dark:border-white/10 dark:bg-white/10 dark:text-white">
                                <button type="button" wire:click="removeTokenOverride({{ $si }}, {{ $oi }})" class="text-red-400 hover:text-red-600">
                                    <x-heroicon-o-x-mark class="h-3.5 w-3.5"/>
                                </button>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Columns and their blocks --}}
                <div x-show="open" x-collapse>
                    <div class="flex gap-0 divide-x divide-gray-100 dark:divide-white/10">

                        @foreach($section['columns'] as $ci => $column)
                            <div class="magna-block-editor__column min-w-0 flex-1 p-3"
                                 style="flex: {{ $column['span'] }} {{ $column['span'] }} 0%">

                                {{-- Column label --}}
                                <div class="mb-2 flex items-center justify-between">
                                    <span class="text-xs text-gray-400">Col {{ $ci + 1 }} (span {{ $column['span'] }})</span>
                                </div>

                                {{-- Blocks in this column --}}
                                @forelse($column['blocks'] as $bi => $block)
                                    <div class="magna-block-editor__block mb-2 rounded-lg border border-gray-200 bg-white dark:border-white/10 dark:bg-white/5"
                                         x-data="{ blockOpen: true }">

                                        {{-- Block header --}}
                                        <div class="flex items-center gap-2 px-3 py-2">
                                            <button type="button" @click="blockOpen = !blockOpen"
                                                    class="flex-1 text-left text-xs font-medium text-gray-700 dark:text-gray-300">
                                                {{ ucfirst($block['block']) }}
                                            </button>
                                            <div class="flex items-center gap-1">
                                                <button type="button" wire:click="moveBlockUp({{ $si }}, {{ $ci }}, {{ $bi }})" title="Move up" class="rounded p-0.5 hover:bg-gray-100 dark:hover:bg-white/10">
                                                    <x-heroicon-o-arrow-up class="h-3 w-3 text-gray-400"/>
                                                </button>
                                                <button type="button" wire:click="moveBlockDown({{ $si }}, {{ $ci }}, {{ $bi }})" title="Move down" class="rounded p-0.5 hover:bg-gray-100 dark:hover:bg-white/10">
                                                    <x-heroicon-o-arrow-down class="h-3 w-3 text-gray-400"/>
                                                </button>
                                                <button type="button" wire:click="duplicateBlock({{ $si }}, {{ $ci }}, {{ $bi }})" title="Duplicate" class="rounded p-0.5 hover:bg-gray-100 dark:hover:bg-white/10">
                                                    <x-heroicon-o-document-duplicate class="h-3 w-3 text-gray-400"/>
                                                </button>
                                                <button type="button"
                                                        wire:click="removeBlock({{ $si }}, {{ $ci }}, {{ $bi }})"
                                                        wire:confirm="Delete this block?"
                                                        title="Delete"
                                                        class="rounded p-0.5 text-red-400 hover:bg-red-50 dark:hover:bg-red-950/30">
                                                    <x-heroicon-o-trash class="h-3 w-3"/>
                                                </button>
                                            </div>
                                        </div>

                                        {{-- Block data fields (simple key-value inputs) --}}
                                        <div x-show="blockOpen" x-collapse class="border-t border-gray-100 px-3 pb-3 pt-2 dark:border-white/10">
                                            @php
                                                $blockDef = app(\Magna\Blocks\BlockRegistry::class)->get($block['block']);
                                            @endphp
                                            @if($blockDef)
                                                @foreach($blockDef->fields as $bField)
                                                    <div class="mb-2">
                                                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                                                            {{ $bField->label }}
                                                            @if($bField->required)<span class="text-red-500">*</span>@endif
                                                        </label>
                                                        @if($bField->type === 'select')
                                                            <select wire:model.live="sections.{{ $si }}.columns.{{ $ci }}.blocks.{{ $bi }}.data.{{ $bField->handle }}"
                                                                    class="w-full rounded border border-gray-200 bg-white px-2 py-1.5 text-xs dark:border-white/10 dark:bg-white/10 dark:text-white">
                                                                @foreach($bField->resolveOptions() as $optVal => $optLabel)
                                                                    <option value="{{ $optVal }}">{{ $optLabel }}</option>
                                                                @endforeach
                                                            </select>
                                                        @elseif($bField->type === 'boolean')
                                                            <input type="checkbox"
                                                                   wire:model.live="sections.{{ $si }}.columns.{{ $ci }}.blocks.{{ $bi }}.data.{{ $bField->handle }}"
                                                                   class="rounded border-gray-300">
                                                        @elseif($bField->type === 'textarea' || $bField->type === 'richtext' || $bField->type === 'json')
                                                            <textarea wire:model.live="sections.{{ $si }}.columns.{{ $ci }}.blocks.{{ $bi }}.data.{{ $bField->handle }}"
                                                                      rows="3"
                                                                      class="w-full rounded border border-gray-200 bg-white px-2 py-1.5 text-xs dark:border-white/10 dark:bg-white/10 dark:text-white"></textarea>
                                                        @else
                                                            <input type="{{ $bField->type === 'number' ? 'number' : 'text' }}"
                                                                   wire:model.live="sections.{{ $si }}.columns.{{ $ci }}.blocks.{{ $bi }}.data.{{ $bField->handle }}"
                                                                   class="w-full rounded border border-gray-200 bg-white px-2 py-1.5 text-xs dark:border-white/10 dark:bg-white/10 dark:text-white">
                                                        @endif
                                                    </div>
                                                @endforeach
                                            @else
                                                <p class="text-xs text-amber-600">Block type "{{ $block['block'] }}" is not registered (plugin may be disabled).</p>
                                            @endif
                                        </div>

                                    </div>
                                @empty
                                    <p class="mb-2 text-xs text-gray-400 italic">No blocks in this column.</p>
                                @endforelse

                                {{-- Add block button --}}
                                <button type="button"
                                        @click="addBlockModal = true; addBlockTarget = { si: {{ $si }}, ci: {{ $ci }} }"
                                        class="flex w-full items-center justify-center gap-1.5 rounded-lg border-2 border-dashed border-gray-200 py-2 text-xs text-gray-400 hover:border-indigo-400 hover:text-indigo-600 dark:border-white/10 dark:hover:border-indigo-500">
                                    <x-heroicon-o-plus class="h-3.5 w-3.5"/>
                                    Add block
                                </button>

                            </div>
                        @endforeach

                    </div>
                </div>

            </div>
        @empty
            <div class="flex flex-col items-center justify-center py-16 text-gray-400">
                <x-heroicon-o-squares-2x2 class="mb-3 h-10 w-10 text-gray-300"/>
                <p class="text-sm">No sections yet. Add one below.</p>
            </div>
        @endforelse

    </div>

    {{-- Add section button --}}
    <div class="border border-t-0 border-gray-200 dark:border-white/10">
        <button type="button"
                wire:click="addSection"
                class="flex w-full items-center justify-center gap-2 rounded-b-lg py-3 text-sm text-gray-500 hover:bg-gray-50 dark:text-gray-400 dark:hover:bg-white/5">
            <x-heroicon-o-plus-circle class="h-4 w-4"/>
            Add section
        </button>
    </div>

    {{-- Add Block Modal --}}
    <div x-show="addBlockModal"
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm"
         @keydown.escape.window="addBlockModal = false">

        <div class="mx-4 w-full max-w-lg rounded-xl border border-gray-200 bg-white shadow-2xl dark:border-white/10 dark:bg-gray-900">
            <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4 dark:border-white/10">
                <h2 class="font-semibold text-gray-900 dark:text-white">Add block</h2>
                <button type="button" @click="addBlockModal = false" class="text-gray-400 hover:text-gray-600">
                    <x-heroicon-o-x-mark class="h-5 w-5"/>
                </button>
            </div>
            <div class="max-h-96 overflow-y-auto p-5">
                @foreach($availableBlocks as $category => $blocks)
                    <div class="mb-4">
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">{{ ucfirst($category) }}</p>
                        <div class="grid grid-cols-3 gap-2">
                            @foreach($blocks as $bDef)
                                <button type="button"
                                        @click="$wire.addBlock(addBlockTarget.si, addBlockTarget.ci, '{{ $bDef['handle'] }}'); addBlockModal = false"
                                        class="flex flex-col items-center rounded-lg border border-gray-100 px-2 py-3 text-xs font-medium text-gray-700 hover:border-indigo-400 hover:bg-indigo-50 dark:border-white/10 dark:text-gray-300 dark:hover:border-indigo-500 dark:hover:bg-indigo-950/30">
                                    <x-dynamic-component :component="$bDef['icon']" class="mb-1.5 h-5 w-5 text-gray-500 dark:text-gray-400"/>
                                    {{ $bDef['label'] }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

    </div>

    {{-- Cloud Library placeholder (Stage 19 replaces this) --}}
    <div x-show="cloudModal"
         x-transition
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm"
         @keydown.escape.window="cloudModal = false">
        <div class="mx-4 w-full max-w-sm rounded-xl border border-gray-200 bg-white p-8 text-center shadow-2xl dark:border-white/10 dark:bg-gray-900">
            <x-heroicon-o-cloud-arrow-down class="mx-auto mb-4 h-12 w-12 text-indigo-400"/>
            <h2 class="mb-2 font-semibold text-gray-900 dark:text-white">Cloud Library</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Cloud Library coming soon. Pre-designed sections will be available here in Stage 19.</p>
            <button type="button" @click="cloudModal = false" class="mt-5 rounded-md bg-gray-100 px-4 py-2 text-sm text-gray-700 hover:bg-gray-200 dark:bg-white/10 dark:text-white">Close</button>
        </div>
    </div>

    {{-- Include the global media picker once --}}
    <livewire:magna-media-picker />

</div>

{{-- Autosave: 3-second debounce after any editor change --}}
<script>
(function () {
    var timer = null;
    document.addEventListener('livewire:update', function (e) {
        clearTimeout(timer);
        timer = setTimeout(function () {
            // Target only the block editor root element (not the outer Filament page component)
            var editorEl = document.querySelector('.magna-block-editor[wire\\:id]');
            if (!editorEl) { return; }
            var editor = Livewire.find(editorEl.getAttribute('wire:id'));
            if (editor && typeof editor.save === 'function') {
                editor.save();
            }
        }, 3000);
    });
})();
</script>
