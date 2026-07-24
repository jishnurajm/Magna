{{-- Block: entries (dynamic content query) --}}
@php
    $entries = $block['_resolved']['entries'] ?? [];
    $display = $block['data']['display'] ?? 'cards';
@endphp
<div class="magna-block magna-block--entries magna-entries magna-entries--{{ $display }}">
    @forelse($entries as $entry)
        <article class="magna-entries__item">
            <h3 class="magna-entries__title">
                <a href="{{ $entry['slug'] ?? '#' }}">{{ $entry['title'] ?? '' }}</a>
            </h3>
            @if(!empty($entry['excerpt']))
                <p class="magna-entries__excerpt">{{ $entry['excerpt'] }}</p>
            @endif
            @if(!empty($entry['published_at']))
                <time class="magna-entries__date" datetime="{{ $entry['published_at'] }}">
                    {{ \Illuminate\Support\Carbon::parse($entry['published_at'])->format('M j, Y') }}
                </time>
            @endif
        </article>
    @empty
        <p class="magna-entries__empty">No entries found.</p>
    @endforelse
</div>
