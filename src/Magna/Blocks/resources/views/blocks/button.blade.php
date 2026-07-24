{{-- Block: button --}}
@php
    $label  = $block['data']['label'] ?? '';
    $url    = \Magna\Blocks\Support\SafeUrl::sanitize($block['data']['url'] ?? '#');
    $style  = $block['data']['style'] ?? 'primary';
    $size   = $block['data']['size'] ?? 'md';
    $target = $block['data']['target'] ?? '_self';
@endphp
<div class="magna-block magna-block--button">
    <a href="{{ $url }}"
       class="magna-btn magna-btn--{{ $style }} magna-btn--{{ $size }}"
       @if($target === '_blank') target="_blank" rel="noopener noreferrer" @endif>
        {{ $label }}
    </a>
</div>
