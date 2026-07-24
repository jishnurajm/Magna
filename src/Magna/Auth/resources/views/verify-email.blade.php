<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Email — {{ config('app.name') }}</title>
</head>
<body>
<p>Please verify your email address by clicking the link we sent you.</p>
@if (session('status') === 'verification-link-sent')
    <p>A new verification link has been sent.</p>
@endif
<form method="POST" action="{{ route('verification.send') }}">
    @csrf
    <button type="submit">Resend verification email</button>
</form>
<form method="POST" action="{{ route('auth.logout') }}">
    @csrf
    <button type="submit">Log out</button>
</form>
</body>
</html>
