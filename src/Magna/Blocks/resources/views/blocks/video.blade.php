{{-- Block: video --}}
@php
    $url      = isset($block['data']['url']) ? \Magna\Blocks\Support\SafeUrl::sanitize($block['data']['url']) : null;
    $mediaId  = $block['data']['media_id'] ?? null;
    $posterId = $block['data']['poster_id'] ?? null;
    $autoplay = (bool) ($block['data']['autoplay'] ?? false);
    $poster   = $posterId ? \Magna\Media\Media::find($posterId) : null;
    $posterUrl = $poster ? \Illuminate\Support\Facades\Storage::disk($poster->disk)->url($poster->path) : null;
    $media    = $mediaId ? \Magna\Media\Media::find($mediaId) : null;
    $fileUrl  = $media ? \Illuminate\Support\Facades\Storage::disk($media->disk)->url($media->path) : null;
@endphp
<div class="magna-block magna-block--video magna-video">
    @if($fileUrl)
        <video @if($posterUrl) poster="{{ $posterUrl }}" @endif
               @if($autoplay) autoplay muted loop playsinline @else controls @endif
               preload="metadata">
            <source src="{{ $fileUrl }}">
        </video>
    @elseif($url)
        <div class="magna-video__embed">
            {{-- Render as a link; the theme will enhance this into an iframe facade --}}
            <a href="{{ $url }}" class="magna-video__link" @if($posterUrl) style="background-image:url('{{ $posterUrl }}')" @endif>
                <span class="magna-video__play-label">Play video</span>
            </a>
        </div>
    @endif
</div>
