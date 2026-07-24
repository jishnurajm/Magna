{{--
    Block: text — richtext body.
    Output is UNESCAPED by design to render HTML markup from the richtext editor.
    Restricted to admin/editor roles only — do NOT expose to untrusted authors.
--}}
<div class="magna-block magna-block--text magna-prose">
    {!! $block['data']['body'] ?? '' !!}
</div>
