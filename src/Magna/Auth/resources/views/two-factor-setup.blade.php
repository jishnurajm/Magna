<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set up two-factor authentication — {{ config('app.name') }}</title>
</head>
<body>
<p>Your role requires two-factor authentication. Scan the QR code below with an authenticator app (Google Authenticator, 1Password, etc.), then enter the 6-digit code it shows to finish setting up your account.</p>

{!! $qrCodeSvg !!}

<p>Can't scan the code? Enter this key manually: <code>{{ $secret }}</code></p>

<form method="POST" action="{{ route('auth.two-factor.setup.store') }}">
    @csrf
    @error('code')<p>{{ $message }}</p>@enderror
    <label>Authentication code <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" autofocus required></label>
    <button type="submit">Confirm</button>
</form>

<form method="POST" action="{{ route('auth.logout') }}">
    @csrf
    <button type="submit">Sign out</button>
</form>
</body>
</html>
