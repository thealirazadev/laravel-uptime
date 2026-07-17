<!doctype html>
<html lang="en">
<body style="font-family: sans-serif; color: #0f172a; line-height: 1.5;">
    @php $m = $payload->monitor; @endphp

    @switch($payload->event)
        @case('incident.opened')
            <h2 style="color:#b91c1c;">Monitor down: {{ $m['name'] }}</h2>
            <p style="font-family: monospace;">{{ $m['url'] }}</p>
            <p>Reason: {{ $payload->incident['summary'] ?? 'check failed' }}</p>
            <p>Since {{ $payload->startedAtHuman() }}.</p>
            @break

        @case('incident.closed')
            <h2 style="color:#15803d;">Recovered: {{ $m['name'] }}</h2>
            <p style="font-family: monospace;">{{ $m['url'] }}</p>
            <p>Down for {{ $payload->durationHuman() ?? 'a moment' }}.</p>
            @break

        @case('ssl.expiry_warning')
            <h2 style="color:#b45309;">SSL expiry: {{ $m['name'] }}</h2>
            <p style="font-family: monospace;">{{ $m['url'] }}</p>
            <p>The certificate expires in {{ $payload->ssl['days_left'] }} days ({{ $payload->sslExpiresAtHuman() }}).</p>
            @break

        @default
            <h2>Test alert</h2>
            <p>This is a test alert from {{ config('app.name') }}. This channel is configured correctly.</p>
    @endswitch

    @isset($m)
        <p><a href="{{ route('monitors.show', $m['id']) }}">View monitor in the dashboard</a></p>
    @endisset
</body>
</html>
