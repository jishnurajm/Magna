{{-- Block: image --}}
@php
    $mediaId = $block['data']['media_id'] ?? null;
    $alt     = $block['data']['alt'] ?? '';
    $caption = $block['data']['caption'] ?? '';
    $display = $block['data']['display'] ?? 'full';
    $media   = $mediaId ? \Magna\Media\Media::find($mediaId) : null;
    $url     = $media ? \Illuminate\Support\Facades\Storage::disk($media->disk)->url($media->path) : null;
@endphp
@if($url)
<figure class="magna-block magna-block--image magna-image magna-image--{{ $display }}">
    <img src="{{ $url }}"
         alt="{{ $alt }}"
         loading="lazy"
         decoding="async"
         @if($media && $media->width && $media->height) width="{{ $media->width }}" height="{{ $media->height }}" @endif>
    @if($caption)
        <figcaption>{{ $caption }}</figcaption>
    @endif
</figure>
@endif
