{{--
    Block: html — intentional raw HTML escape hatch.
    Output is UNESCAPED by design; this block is restricted to admin/editor roles only.
    Do NOT expose this block to untrusted content authors.
--}}
<div class="magna-block magna-block--html magna-html">
    {!! $block['data']['content'] ?? '' !!}
</div>
