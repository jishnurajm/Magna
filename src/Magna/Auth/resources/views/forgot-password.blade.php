<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password — {{ config('app.name') }}</title>
</head>
<body>
@if (session('status'))<p>{{ session('status') }}</p>@endif
<form method="POST" action="{{ route('password.email') }}">
    @csrf
    @error('email')<p>{{ $message }}</p>@enderror
    <label>Email <input type="email" name="email" value="{{ old('email') }}" required autofocus></label>
    <button type="submit">Send reset link</button>
</form>
</body>
</html>
