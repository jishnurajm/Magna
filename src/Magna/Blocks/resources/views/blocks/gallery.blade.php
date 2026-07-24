{{-- Block: gallery --}}
@php
    $imageIds = $block['data']['images'] ?? [];
    $columns  = (int) ($block['data']['columns'] ?? 3);
    $lightbox = (bool) ($block['data']['lightbox'] ?? true);
    if (!is_array($imageIds)) { $imageIds = []; }
    $mediaItems = \Magna\Media\Media::findMany($imageIds);
@endphp
@if($mediaItems->isNotEmpty())
<div class="magna-block magna-block--gallery magna-gallery magna-gallery--cols-{{ $columns }}">
    @foreach($mediaItems as $item)
        @php $url = \Illuminate\Support\Facades\Storage::disk($item->disk)->url($item->path); @endphp
        @if($lightbox)
            <a href="{{ $url }}" class="magna-gallery__item">
                <img src="{{ $url }}" alt="{{ $item->alt ?? '' }}" loading="lazy" decoding="async">
            </a>
        @else
            <div class="magna-gallery__item">
                <img src="{{ $url }}" alt="{{ $item->alt ?? '' }}" loading="lazy" decoding="async">
            </div>
        @endif
    @endforeach
</div>
@endif
