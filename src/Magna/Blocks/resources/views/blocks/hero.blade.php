{{-- Block: hero --}}
@php
    $layout   = $block['data']['layout'] ?? 'centered';
    $headline = $block['data']['headline'] ?? '';
    $sub      = $block['data']['subheadline'] ?? '';
    $ctaPLabel = $block['data']['cta_primary_label'] ?? '';
    $ctaPUrl   = \Magna\Blocks\Support\SafeUrl::sanitize($block['data']['cta_primary_url'] ?? '#');
    $ctaSLabel = $block['data']['cta_secondary_label'] ?? '';
    $ctaSUrl   = \Magna\Blocks\Support\SafeUrl::sanitize($block['data']['cta_secondary_url'] ?? '#');
@endphp
<div class="magna-block magna-block--hero magna-hero magna-hero--{{ $layout }}">
    <div class="magna-hero__content">
        @if($headline)
            <h1 class="magna-hero__headline">{{ $headline }}</h1>
        @endif
        @if($sub)
            <p class="magna-hero__subheadline">{{ $sub }}</p>
        @endif
        @if($ctaPLabel || $ctaSLabel)
            <div class="magna-hero__ctas">
                @if($ctaPLabel)
                    <a href="{{ $ctaPUrl }}" class="magna-btn magna-btn--primary">{{ $ctaPLabel }}</a>
                @endif
                @if($ctaSLabel)
                    <a href="{{ $ctaSUrl }}" class="magna-btn magna-btn--outline">{{ $ctaSLabel }}</a>
                @endif
            </div>
        @endif
    </div>
</div>
