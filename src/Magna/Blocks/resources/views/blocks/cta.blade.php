{{-- Block: cta (call to action) --}}
@php
    $layout   = $block['data']['layout'] ?? 'centered';
    $headline = $block['data']['headline'] ?? '';
    $body     = $block['data']['body'] ?? '';
    $btn1L    = $block['data']['button_primary_label'] ?? '';
    $btn1U    = \Magna\Blocks\Support\SafeUrl::sanitize($block['data']['button_primary_url'] ?? '#');
    $btn2L    = $block['data']['button_secondary_label'] ?? '';
    $btn2U    = \Magna\Blocks\Support\SafeUrl::sanitize($block['data']['button_secondary_url'] ?? '#');
@endphp
<div class="magna-block magna-block--cta magna-cta magna-cta--{{ $layout }}">
    @if($headline)
        <h2 class="magna-cta__headline">{{ $headline }}</h2>
    @endif
    @if($body)
        <p class="magna-cta__body">{{ $body }}</p>
    @endif
    @if($btn1L || $btn2L)
        <div class="magna-cta__actions">
            @if($btn1L)<a href="{{ $btn1U }}" class="magna-btn magna-btn--primary">{{ $btn1L }}</a>@endif
            @if($btn2L)<a href="{{ $btn2U }}" class="magna-btn magna-btn--outline">{{ $btn2L }}</a>@endif
        </div>
    @endif
</div>
