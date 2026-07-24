<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign in — {{ config('app.name') }}</title>
</head>
<body>
<form method="POST" action="{{ route('auth.login.attempt') }}">
    @csrf
    @if ($errors->any())
        <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    @endif
    <label>Email <input type="email" name="email" value="{{ old('email') }}" required autofocus></label>
    <label>Password <input type="password" name="password" required></label>
    <label><input type="checkbox" name="remember"> Remember me</label>
    <button type="submit">Sign in</button>
    <a href="{{ route('password.request') }}">Forgot password?</a>
</form>
</body>
</html>
