<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Upcoming Schedule</x-slot>

        @if($rows === [])
            <p class="text-sm text-gray-500 dark:text-gray-400">No scheduled events in the next period.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="pb-2 pr-4 font-medium text-gray-500 dark:text-gray-400">Entry</th>
                            <th class="pb-2 pr-4 font-medium text-gray-500 dark:text-gray-400">Type</th>
                            <th class="pb-2 pr-4 font-medium text-gray-500 dark:text-gray-400">Event</th>
                            <th class="pb-2 pr-4 font-medium text-gray-500 dark:text-gray-400">Locale</th>
                            <th class="pb-2 font-medium text-gray-500 dark:text-gray-400">Scheduled at</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rows as $row)
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="py-2 pr-4 font-mono text-xs text-gray-700 dark:text-gray-300 max-w-[12rem] truncate">
                                    {{ $row['label'] }}
                                </td>
                                <td class="py-2 pr-4 text-gray-700 dark:text-gray-300">{{ $row['type'] }}</td>
                                <td class="py-2 pr-4">
                                    @if($row['status'] === 'publish')
                                        <span class="inline-flex items-center rounded-full bg-info-100 px-2 py-0.5 text-xs font-medium text-info-700 dark:bg-info-900 dark:text-info-200">
                                            Publish
                                        </span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-warning-100 px-2 py-0.5 text-xs font-medium text-warning-700 dark:bg-warning-900 dark:text-warning-200">
                                            Unpublish
                                        </span>
                                    @endif
                                </td>
                                <td class="py-2 pr-4 text-gray-500 dark:text-gray-400">
                                    {{ $row['locale'] !== '' ? $row['locale'] : '—' }}
                                </td>
                                <td class="py-2 text-gray-700 dark:text-gray-300">
                                    {{ $row['at']->format('d M Y H:i') }}
                                    <span class="text-xs text-gray-400 dark:text-gray-500 ml-1">({{ $row['at']->diffForHumans() }})</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
