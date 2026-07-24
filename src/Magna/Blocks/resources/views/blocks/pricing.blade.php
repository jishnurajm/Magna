{{-- Block: pricing --}}
@php $tiers = json_decode($block['data']['tiers'] ?? '[]', true) ?: []; @endphp
<div class="magna-block magna-block--pricing magna-pricing">
    @foreach($tiers as $tier)
        <div class="magna-pricing__tier @if(!empty($tier['highlighted'])) magna-pricing__tier--highlighted @endif">
            <h3 class="magna-pricing__name">{{ $tier['name'] ?? '' }}</h3>
            <div class="magna-pricing__price">
                <span class="magna-pricing__amount">{{ $tier['price'] ?? '' }}</span>
                @if(!empty($tier['period']))<span class="magna-pricing__period">/ {{ $tier['period'] }}</span>@endif
            </div>
            @if(!empty($tier['features']))
                <ul class="magna-pricing__features">
                    @foreach((array) $tier['features'] as $feature)
                        <li>{{ $feature }}</li>
                    @endforeach
                </ul>
            @endif
            @if(!empty($tier['cta_label']))
                <a href="{{ $tier['cta_url'] ?? '#' }}" class="magna-btn magna-btn--{{ empty($tier['highlighted']) ? 'outline' : 'primary' }}">
                    {{ $tier['cta_label'] }}
                </a>
            @endif
        </div>
    @endforeach
</div>
