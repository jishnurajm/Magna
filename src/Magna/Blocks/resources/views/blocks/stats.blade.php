{{-- Block: stats --}}
@php
    $items   = json_decode($block['data']['items'] ?? '[]', true) ?: [];
    $columns = (int) ($block['data']['columns'] ?? 3);
@endphp
<div class="magna-block magna-block--stats magna-stats magna-stats--cols-{{ $columns }}">
    @foreach($items as $item)
        <div class="magna-stats__item">
            @if(!empty($item['icon']))<span class="magna-stats__icon" aria-hidden="true">{{ $item['icon'] }}</span>@endif
            <span class="magna-stats__value">{{ $item['value'] ?? '' }}</span>
            <span class="magna-stats__label">{{ $item['label'] ?? '' }}</span>
        </div>
    @endforeach
</div>
