<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $status['group']['name'] }} status</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    @php
        [$overallClass, $overallText] = match ($status['overall']) {
            'operational' => ['badge-up', 'All systems operational'],
            'down' => ['badge-down', 'Some systems are down'],
            default => ['badge-neutral', 'Status not yet determined'],
        };
    @endphp

    <main class="status-page">
        <header>
            <h1>{{ $status['group']['name'] }}</h1>
            <p class="overall">
                <span class="badge {{ $overallClass }}">{{ $overallText }}</span>
            </p>
            <p class="meta">
                Updated {{ \Illuminate\Support\Carbon::parse($status['generated_at'])->format('Y-m-d H:i:s') }} UTC
            </p>
        </header>

        <section aria-label="Monitors">
            @forelse ($status['monitors'] as $monitor)
                @php
                    [$badgeClass, $badgeLabel] = match ($monitor['status']) {
                        'up' => ['badge-up', 'Up'],
                        'down' => ['badge-down', 'Down'],
                        default => ['badge-neutral', 'Unknown'],
                    };
                @endphp
                <div class="status-row">
                    <div>
                        <strong>{{ $monitor['name'] }}</strong>
                        <span class="badge {{ $badgeClass }}">{{ $badgeLabel }}</span>
                    </div>
                    <div class="uptimes">
                        <span>24h: {{ $monitor['uptime']['day'] !== null ? number_format($monitor['uptime']['day'], 2).'%' : '—' }}</span>
                        <span>7d: {{ $monitor['uptime']['week'] !== null ? number_format($monitor['uptime']['week'], 2).'%' : '—' }}</span>
                        <span>30d: {{ $monitor['uptime']['month'] !== null ? number_format($monitor['uptime']['month'], 2).'%' : '—' }}</span>
                        <span>{{ $monitor['avg_response_time_ms']['day'] !== null ? $monitor['avg_response_time_ms']['day'].' ms' : '—' }}</span>
                    </div>
                </div>
            @empty
                <p class="empty">No monitors are being reported for this page yet.</p>
            @endforelse
        </section>

        @if (! empty($status['incidents']))
            <section aria-label="Recent incidents">
                <h2>Recent incidents</h2>
                @foreach ($status['incidents'] as $incident)
                    <div class="status-row @if ($incident['status'] === 'open') incident-open @endif">
                        <div>
                            <strong>{{ $incident['monitor'] }}</strong>
                            <span class="badge {{ $incident['status'] === 'open' ? 'badge-down' : 'badge-neutral' }}">
                                {{ ucfirst($incident['status']) }}
                            </span>
                        </div>
                        <div class="meta mono">
                            {{ \Illuminate\Support\Carbon::parse($incident['started_at'])->format('Y-m-d H:i') }} UTC
                            @if ($incident['closed_at'])
                                &ndash; {{ \Illuminate\Support\Carbon::parse($incident['closed_at'])->format('Y-m-d H:i') }} UTC
                            @endif
                        </div>
                    </div>
                @endforeach
            </section>
        @endif
    </main>
</body>
</html>
