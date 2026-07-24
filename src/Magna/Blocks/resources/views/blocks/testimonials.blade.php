{{-- Block: testimonials --}}
@php
    $items  = json_decode($block['data']['items'] ?? '[]', true) ?: [];
    $layout = $block['data']['layout'] ?? 'cards';
@endphp
<div class="magna-block magna-block--testimonials magna-testimonials magna-testimonials--{{ $layout }}">
    @foreach($items as $item)
        <blockquote class="magna-testimonials__item">
            <p class="magna-testimonials__quote">{{ $item['quote'] ?? '' }}</p>
            <footer class="magna-testimonials__author">
                <strong>{{ $item['author'] ?? '' }}</strong>
                @if(!empty($item['role']))<span>, {{ $item['role'] }}</span>@endif
            </footer>
        </blockquote>
    @endforeach
</div>
