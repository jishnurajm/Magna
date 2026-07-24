{{-- Block: heading --}}
@php
    $level = in_array($block['data']['level'] ?? 'h2', ['h1','h2','h3','h4','h5','h6']) ? ($block['data']['level'] ?? 'h2') : 'h2';
    $align = $block['data']['align'] ?? 'left';
    $text  = $block['data']['text'] ?? '';
@endphp
<div class="magna-block magna-block--heading magna-heading magna-heading--{{ $align }}">
    <{{ $level }}>{{ $text }}</{{ $level }}>
</div>
