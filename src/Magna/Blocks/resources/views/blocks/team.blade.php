{{-- Block: team --}}
@php
    $members = json_decode($block['data']['members'] ?? '[]', true) ?: [];
    $columns = (int) ($block['data']['columns'] ?? 3);
@endphp
<div class="magna-block magna-block--team magna-team magna-team--cols-{{ $columns }}">
    @foreach($members as $member)
        @php
            $photoMedia = !empty($member['photo_id']) ? \Magna\Media\Media::find($member['photo_id']) : null;
            $photoUrl   = $photoMedia ? \Illuminate\Support\Facades\Storage::disk($photoMedia->disk)->url($photoMedia->path) : null;
        @endphp
        <div class="magna-team__member">
            @if($photoUrl)
                <img src="{{ $photoUrl }}" alt="{{ $member['name'] ?? '' }}" loading="lazy" decoding="async" class="magna-team__photo">
            @endif
            <h3 class="magna-team__name">{{ $member['name'] ?? '' }}</h3>
            @if(!empty($member['role']))<p class="magna-team__role">{{ $member['role'] }}</p>@endif
            @if(!empty($member['bio']))<p class="magna-team__bio">{{ $member['bio'] }}</p>@endif
        </div>
    @endforeach
</div>
