<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Two-factor authentication enabled — {{ config('app.name') }}</title>
</head>
<body>
<p>Two-factor authentication is now enabled on your account. Save these recovery codes somewhere safe — each one can be used once if you lose access to your authenticator app. They will not be shown again.</p>

<ul>
    @foreach ($recoveryCodes as $code)
        <li><code>{{ $code }}</code></li>
    @endforeach
</ul>

<a href="{{ route('dashboard') }}">Continue to dashboard</a>
</body>
</html>
