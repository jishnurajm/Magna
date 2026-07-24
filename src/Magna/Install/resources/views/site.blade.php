@extends('magna-install::layout')

@section('content')
    <h1>About your site</h1>
    <p class="lead">These can be changed later in your settings.</p>

    <form method="POST" action="/install/site">
        @csrf

        <div class="field">
            <label for="name">Site name</label>
            <input type="text" id="name" name="name" value="{{ old('name') }}" placeholder="My Magna Site" required autofocus>
        </div>

        <div class="field">
            <label for="url">Site URL</label>
            <input type="url" id="url" name="url" value="{{ old('url', $defaultUrl) }}" required>
            <div class="hint">The address visitors and API clients will use to reach this site.</div>
        </div>

        <div class="field checkbox">
            <input type="checkbox" id="production" name="production" value="1" @checked(old('production', true))>
            <label for="production">
                This is a production site
                <div class="hint">Enables production mode and hides detailed error pages from visitors. Leave unchecked for local development.</div>
            </label>
        </div>

        <button type="submit" class="btn">Continue</button>
    </form>
@endsection
