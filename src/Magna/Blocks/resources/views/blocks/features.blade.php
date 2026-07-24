{{-- Block: features --}}
@php
    $items  = json_decode($block['data']['items'] ?? '[]', true) ?: [];
    $layout = $block['data']['layout'] ?? 'icon-grid';
@endphp
<div class="magna-block magna-block--features magna-features magna-features--{{ $layout }}">
    @foreach($items as $item)
        <div class="magna-features__item">
            @if(!empty($item['icon']))<span class="magna-features__icon" aria-hidden="true">{{ $item['icon'] }}</span>@endif
            @if(!empty($item['title']))<h3 class="magna-features__title">{{ $item['title'] }}</h3>@endif
            @if(!empty($item['description']))<p class="magna-features__description">{{ $item['description'] }}</p>@endif
        </div>
    @endforeach
</div>
