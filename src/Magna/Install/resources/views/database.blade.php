@extends('magna-install::layout')

@section('content')
    <h1>Connect your database</h1>
    <p class="lead">Magna tests the connection before saving anything, then sets up its tables automatically.</p>

    <form method="POST" action="/install/database">
        @csrf

        <div class="drivers">
            @foreach ([
                'pgsql' => ['PostgreSQL', 'recommended'],
                'mysql' => ['MySQL', '8.0+'],
                'mariadb' => ['MariaDB', '10.6+'],
                'sqlite' => ['SQLite', 'zero config'],
            ] as $value => [$label, $sub])
                <span>
                    <input type="radio" id="driver-{{ $value }}" name="driver" value="{{ $value }}" @checked(old('driver', 'pgsql') === $value)>
                    <label for="driver-{{ $value }}">{{ $label }}<small>{{ $sub }}</small></label>
                </span>
            @endforeach
        </div>

        <div id="server-fields">
            <div class="grid-2">
                <div class="field">
                    <label for="host">Host</label>
                    <input type="text" id="host" name="host" value="{{ old('host', '127.0.0.1') }}">
                </div>
                <div class="field">
                    <label for="port">Port</label>
                    <input type="number" id="port" name="port" value="{{ old('port', '5432') }}">
                </div>
            </div>

            <div class="field">
                <label for="database">Database name</label>
                <input type="text" id="database" name="database" value="{{ old('database') }}" placeholder="magna">
                <div class="hint">The database must already exist — create it in your hosting panel if you haven't.</div>
            </div>

            <div class="grid-2">
                <div class="field">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" value="{{ old('username') }}">
                </div>
                <div class="field">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password">
                </div>
            </div>
        </div>

        <div id="sqlite-fields" style="display:none;">
            <div class="field">
                <label for="sqlite_path">Database file path</label>
                <input type="text" id="sqlite_path" name="sqlite_path" value="{{ old('sqlite_path', $defaultSqlitePath) }}">
                <div class="hint">Created automatically if it doesn't exist. SQLite is great for small sites and trying Magna out.</div>
            </div>
        </div>

        <button type="submit" class="btn">Test connection &amp; continue</button>
    </form>
@endsection

@section('scripts')
    <script>
        (function () {
            var ports = { pgsql: '5432', mysql: '3306', mariadb: '3306' };
            var radios = document.querySelectorAll('input[name=driver]');
            var server = document.getElementById('server-fields');
            var sqlite = document.getElementById('sqlite-fields');
            var port = document.getElementById('port');

            function sync() {
                var driver = document.querySelector('input[name=driver]:checked').value;
                var isSqlite = driver === 'sqlite';
                server.style.display = isSqlite ? 'none' : '';
                sqlite.style.display = isSqlite ? '' : 'none';
                if (!isSqlite && ports[driver] && ['5432', '3306'].indexOf(port.value) !== -1) {
                    port.value = ports[driver];
                }
            }

            radios.forEach(function (r) { r.addEventListener('change', sync); });
            sync();
        })();
    </script>
@endsection
