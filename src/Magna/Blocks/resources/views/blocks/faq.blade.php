{{-- Block: faq (native <details>/<summary>, zero JS) --}}
@php $items = json_decode($block['data']['items'] ?? '[]', true) ?: []; @endphp
<div class="magna-block magna-block--faq magna-faq">
    @foreach($items as $item)
        <details class="magna-faq__item">
            <summary class="magna-faq__question">{{ $item['question'] ?? '' }}</summary>
            <div class="magna-faq__answer">{{ $item['answer'] ?? '' }}</div>
        </details>
    @endforeach
</div>
