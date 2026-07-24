{{-- Block: logos --}}
@php
    $items = json_decode($block['data']['items'] ?? '[]', true) ?: [];
    $style = $block['data']['style'] ?? 'grayscale';
@endphp
<div class="magna-block magna-block--logos magna-logos magna-logos--{{ $style }}">
    @foreach($items as $item)
        @php
            $logoMedia = !empty($item['image_id']) ? \Magna\Media\Media::find($item['image_id']) : null;
            $logoUrl   = $logoMedia ? \Illuminate\Support\Facades\Storage::disk($logoMedia->disk)->url($logoMedia->path) : null;
        @endphp
        @if($logoUrl)
            @if(!empty($item['url']))
                <a href="{{ $item['url'] }}" class="magna-logos__item" target="_blank" rel="noopener noreferrer">
                    <img src="{{ $logoUrl }}" alt="{{ $item['alt'] ?? '' }}" loading="lazy" decoding="async">
                </a>
            @else
                <div class="magna-logos__item">
                    <img src="{{ $logoUrl }}" alt="{{ $item['alt'] ?? '' }}" loading="lazy" decoding="async">
                </div>
            @endif
        @endif
    @endforeach
</div>
