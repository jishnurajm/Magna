<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password — {{ config('app.name') }}</title>
</head>
<body>
<form method="POST" action="{{ route('password.update') }}">
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">
    @if ($errors->any())
        <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    @endif
    <label>Email <input type="email" name="email" value="{{ $email ?? old('email') }}" required autofocus></label>
    <label>New password <input type="password" name="password" required></label>
    <label>Confirm password <input type="password" name="password_confirmation" required></label>
    <button type="submit">Reset password</button>
</form>
</body>
</html>
