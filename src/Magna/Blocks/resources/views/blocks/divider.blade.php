{{-- Block: divider --}}
@php $style = $block['data']['style'] ?? 'solid'; @endphp
<div class="magna-block magna-block--divider">
    @if($style === 'icon-center')
        @php $icon = $block['data']['icon'] ?? 'heroicon-o-star'; @endphp
        <div class="magna-divider magna-divider--icon-center" role="separator" aria-hidden="true">
            <hr class="magna-divider__line">
            <span class="magna-divider__icon">⭐</span>
            <hr class="magna-divider__line">
        </div>
    @else
        <hr class="magna-divider magna-divider--{{ $style }}" role="separator" aria-hidden="true">
    @endif
</div>
