<x-filament-panels::page>
    <style>
        .fi-section { scroll-margin-top: 6rem; }
    </style>

    <div
        x-data="{
            q: '',
            filter() {
                const term = this.q.trim().toLowerCase();
                this.$el.querySelectorAll('.fi-section').forEach((section) => {
                    const match = term === '' || section.textContent.toLowerCase().includes(term);
                    // Hide the whole schema-component wrapper so no empty grid
                    // gap is left behind where a section is filtered out.
                    const wrapper = section.closest('.fi-sc-component') || section;
                    wrapper.style.display = match ? '' : 'none';
                });
            },
            init() {
                this.$watch('q', () => this.filter());
            },
        }"
    >
        {{-- Settings search --}}
        <div class="mb-6 max-w-md">
            <label class="relative block">
                <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-400">
                    @svg('heroicon-o-magnifying-glass', 'h-5 w-5')
                </span>
                <input
                    type="search"
                    x-model="q"
                    placeholder="Search settings…"
                    class="w-full rounded-xl border border-gray-300 bg-white py-2.5 pl-10 pr-3 text-sm text-gray-900 shadow-sm outline-none transition placeholder:text-gray-400 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 dark:border-white/10 dark:bg-white/5 dark:text-gray-100 dark:placeholder:text-gray-500"
                >
            </label>
        </div>

        {{ $this->form }}
    </div>
</x-filament-panels::page>
