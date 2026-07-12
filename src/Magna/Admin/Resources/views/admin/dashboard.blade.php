<x-filament-panels::page>
    @assets
        <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
    @endassets

    {{-- Masonry (CSS multi-column) layout: each column stacks independently, so a
         tall widget in one column no longer stretches the row and leaves a big gap
         above a widget stacked beneath a short one. A single container keeps drag
         reordering working. Utilities are inline/`<style>` because Filament's
         compiled theme CSS does not include arbitrary Tailwind column utilities. --}}
    <style>
        #magna-dashboard-widgets {
            column-gap: 1.5rem;
        }
        #magna-dashboard-widgets > [data-widget-key] {
            break-inside: avoid;
            -webkit-column-break-inside: avoid;
            page-break-inside: avoid;
            margin-bottom: 1.5rem;
        }
        /* Sortable's fallback drag clone is moved to <body>; keep it un-clipped. */
        #magna-dashboard-widgets > [data-widget-key].sortable-drag {
            break-inside: auto;
            margin-bottom: 0;
        }
    </style>

    <div
        id="magna-dashboard-widgets"
        style="column-count: {{ $this->getColumns() }}"
    >
        @foreach ($this->getVisibleWidgets() as $widgetClass)
            <div
                data-widget-key="{{ class_basename($widgetClass) }}"
                class="relative group"
            >
                {{-- Drag handle — always present so it is easy to grab; it stays
                     subtle until the widget is hovered. --}}
                <button
                    data-sort-handle
                    type="button"
                    title="Drag to reorder"
                    {{-- z-index set inline: the Tailwind z-* utilities are not part of
                         Filament's compiled theme CSS, so a class would be a no-op and
                         the widget card would paint over the handle. --}}
                    style="z-index: 30"
                    class="absolute top-3 right-3 cursor-grab active:cursor-grabbing rounded-md bg-white/80 dark:bg-gray-800/80 backdrop-blur p-1.5 shadow-sm border border-gray-200 dark:border-gray-700 text-gray-300 dark:text-gray-600 opacity-40 group-hover:opacity-100 hover:text-gray-600 dark:hover:text-gray-300 transition-all duration-150"
                >
                    <svg class="w-3.5 h-3.5" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                        <circle cx="5" cy="3" r="1.5"/>
                        <circle cx="11" cy="3" r="1.5"/>
                        <circle cx="5" cy="8" r="1.5"/>
                        <circle cx="11" cy="8" r="1.5"/>
                        <circle cx="5" cy="13" r="1.5"/>
                        <circle cx="11" cy="13" r="1.5"/>
                    </svg>
                </button>

                <livewire:dynamic-component :is="$widgetClass" :key="$widgetClass" />
            </div>
        @endforeach
    </div>

    @script
    <script>
        (function () {
            const grid = document.getElementById('magna-dashboard-widgets');

            if (! grid || typeof Sortable === 'undefined') return;

            // forceFallback avoids the native HTML5 drag-and-drop API, which is
            // unreliable with hover handles and Livewire-morphed content. The
            // pointer-based fallback drags consistently across browsers + touch.
            new Sortable(grid, {
                animation: 150,
                handle: '[data-sort-handle]',
                draggable: '[data-widget-key]',
                forceFallback: true,
                fallbackTolerance: 3,
                ghostClass: 'opacity-40',
                chosenClass: 'ring-2',
                dragClass: 'shadow-xl',
                onEnd: function () {
                    const order = Array.from(grid.children)
                        .filter(el => el.dataset.widgetKey)
                        .map(el => el.dataset.widgetKey);
                    $wire.reorderWidgets(order);
                },
            });
        })();
    </script>
    @endscript
</x-filament-panels::page>
