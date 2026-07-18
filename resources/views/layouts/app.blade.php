<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Dashboard') — {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    <a class="skip-link" href="#main">Skip to content</a>

    <header class="site-header">
        <div class="bar">
            <a class="brand" href="{{ route('dashboard') }}">{{ config('app.name') }}</a>
            <nav class="site-nav" aria-label="Primary">
                <a href="{{ route('dashboard') }}" @if(request()->routeIs('dashboard')) aria-current="page" @endif>Dashboard</a>
                @if(Route::has('monitors.index'))
                    <a href="{{ route('monitors.index') }}" @if(request()->routeIs('monitors.*')) aria-current="page" @endif>Monitors</a>
                @endif
                @if(Route::has('channels.index'))
                    <a href="{{ route('channels.index') }}" @if(request()->routeIs('channels.*')) aria-current="page" @endif>Channels</a>
                @endif
                @if(Route::has('groups.index'))
                    <a href="{{ route('groups.index') }}" @if(request()->routeIs('groups.*')) aria-current="page" @endif>Groups</a>
                @endif
                @if(Route::has('incidents.index'))
                    <a href="{{ route('incidents.index') }}" @if(request()->routeIs('incidents.*')) aria-current="page" @endif>Incidents</a>
                @endif
            </nav>
            <div class="spacer"></div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn btn-secondary btn-sm">Log out</button>
            </form>
        </div>
    </header>

    <main id="main" class="container">
        @include('partials.flash')
        @yield('content')
    </main>

    <script>
        document.addEventListener('submit', function (e) {
            var msg = e.target.getAttribute('data-confirm');
            if (msg && !window.confirm(msg)) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>
