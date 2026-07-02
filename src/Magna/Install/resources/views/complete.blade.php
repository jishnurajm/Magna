@extends('magna-install::layout')

@section('content')
    <svg class="success-icon" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <defs>
            <linearGradient id="sg" x1="0" y1="0" x2="64" y2="64">
                <stop stop-color="#34d399"/><stop offset="1" stop-color="#6366f1"/>
            </linearGradient>
        </defs>
        <circle cx="32" cy="32" r="29" stroke="url(#sg)" stroke-width="3.5" fill="rgba(52,211,153,.08)"/>
        <path d="M20 33.5 28.5 42 45 24" stroke="url(#sg)" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>

    <h1 style="text-align:center;">Magna is installed 🎉</h1>
    <p class="lead" style="text-align:center;">Your content platform is ready. The installer has locked itself and can't be run again.</p>

    <ul class="summary">
        <li><span>Site</span> <strong>{{ config('app.name') }}</strong></li>
        <li><span>Content API</span> <code>{{ rtrim((string) config('app.url'), '/') }}/api/v1</code></li>
        <li><span>Admin panel</span> <code>/admin</code> <span class="hint">(arrives with the admin release)</span></li>
    </ul>

    <a class="btn" href="/">Visit your site</a>

    <p class="foot" style="margin-top:16px;">
        Next: model your first content type &middot; read the docs to build a frontend against the API
    </p>
@endsection
