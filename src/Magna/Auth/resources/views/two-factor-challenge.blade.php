<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Two-Factor Challenge — {{ config('app.name') }}</title>
</head>
<body>
<form method="POST" action="{{ route('auth.two-factor.challenge.verify') }}">
    @csrf
    @error('code')<p>{{ $message }}</p>@enderror
    <p>Enter the 6-digit code from your authenticator app, or a recovery code.</p>
    <label>Authentication code <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" autofocus></label>
    <p>Or use a recovery code:</p>
    <label>Recovery code <input type="text" name="recovery_code" autocomplete="off"></label>
    <button type="submit">Verify</button>
</form>
</body>
</html>
