@extends('magna-install::layout')

@section('content')
    <h1>Create your admin account</h1>
    <p class="lead">This account gets super-admin access — it bypasses every permission check. You can add regular team members later.</p>

    <form method="POST" action="/install/account">
        @csrf

        <div class="field">
            <label for="name">Your name</label>
            <input type="text" id="name" name="name" value="{{ old('name') }}" required autofocus>
        </div>

        <div class="field">
            <label for="email">Email address</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" required>
        </div>

        <div class="field">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" minlength="12" required>
            <div class="hint">At least 12 characters. A password manager's generated password is ideal.</div>
        </div>

        <div class="field">
            <label for="password_confirmation">Confirm password</label>
            <input type="password" id="password_confirmation" name="password_confirmation" minlength="12" required>
        </div>

        <button type="submit" class="btn">Create account &amp; finish installation</button>
    </form>
@endsection
