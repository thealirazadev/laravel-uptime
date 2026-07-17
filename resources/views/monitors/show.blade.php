@extends('layouts.app')

@section('title', $monitor->name)

@section('content')
    <div class="page-head">
        <h1>{{ $monitor->name }} @include('partials.monitor-status')</h1>
        <a class="btn btn-secondary" href="{{ route('monitors.edit', $monitor) }}">Edit</a>
    </div>

    <div class="card">
        <h2>Details</h2>
        <div class="stat-grid">
            <div class="stat">
                <div class="label">URL</div>
                <div class="value mono" style="font-size:0.9rem; word-break:break-all">{{ $monitor->url }}</div>
            </div>
            <div class="stat">
                <div class="label">Interval</div>
                <div class="value">{{ \App\Models\Monitor::intervalOptions()[$monitor->interval_seconds] ?? $monitor->interval_seconds.'s' }}</div>
            </div>
            <div class="stat">
                <div class="label">Expected status</div>
                <div class="value">{{ $monitor->expected_status }}</div>
            </div>
            <div class="stat">
                <div class="label">Threshold</div>
                <div class="value">{{ $monitor->confirmation_threshold }}</div>
            </div>
            <div class="stat">
                <div class="label">Last checked</div>
                <div class="value" style="font-size:1rem">{{ $monitor->last_checked_at?->format('Y-m-d H:i:s').' UTC' ?? 'Never' }}</div>
            </div>
            @if ($monitor->last_error)
                <div class="stat">
                    <div class="label">Last error</div>
                    <div class="value mono" style="font-size:0.9rem; color:var(--down)">{{ $monitor->last_error }}</div>
                </div>
            @endif
            @if ($monitor->isHttps())
                <div class="stat">
                    <div class="label">SSL expires</div>
                    <div class="value" style="font-size:1rem">
                        @if ($monitor->ssl_expires_at)
                            @php $daysLeft = \App\Models\Monitor::sslDaysLeft($monitor->ssl_expires_at); @endphp
                            {{ $monitor->ssl_expires_at->format('Y-m-d') }} UTC
                            <span class="meta" @if ($daysLeft <= 14) style="color:var(--warn)" @endif>({{ $daysLeft }} days left)</span>
                        @else
                            <span class="meta">Not checked yet</span>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>

    <div class="card">
        <h2>Response time &amp; uptime</h2>
        <h3 style="font-size:0.875rem; color:var(--text-muted); margin-bottom:4px">Response time, last 24 hours</h3>
        {{-- Chart output interpolates only computed numbers; safe to render unescaped. --}}
        {!! $charts['response_day'] !!}
        <h3 style="font-size:0.875rem; color:var(--text-muted); margin:16px 0 4px">Response time, last 30 days</h3>
        {!! $charts['response_month'] !!}
        <h3 style="font-size:0.875rem; color:var(--text-muted); margin:16px 0 4px">Uptime, last 30 days</h3>
        {!! $charts['uptime_month'] !!}
    </div>

    <div class="card">
        <h2>Alert channels</h2>
        @if ($monitor->channels->isEmpty())
            <p class="meta">No channels attached. Edit the monitor to route alerts.</p>
        @else
            <ul>
                @foreach ($monitor->channels as $channel)
                    <li>
                        {{ $channel->name }}
                        <span class="meta">({{ ucfirst($channel->type) }}{{ $channel->is_enabled ? '' : ', disabled' }})</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <div class="card">
        <h2>Incidents</h2>
        @if ($incidents->isEmpty())
            <p class="empty">No incidents recorded.</p>
        @else
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Started</th>
                            <th scope="col">Resolved</th>
                            <th scope="col">Summary</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($incidents as $incident)
                            <tr @class(['incident-open' => $incident->isOpen()])>
                                <td class="mono">
                                    @if (Route::has('incidents.show'))
                                        <a href="{{ route('incidents.show', $incident) }}">{{ $incident->started_at->format('Y-m-d H:i') }}</a>
                                    @else
                                        {{ $incident->started_at->format('Y-m-d H:i') }}
                                    @endif
                                </td>
                                <td class="mono">{{ $incident->closed_at?->format('Y-m-d H:i') ?? 'Open' }}</td>
                                <td class="mono">{{ $incident->summary }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="card">
        <h2>Recent checks</h2>
        @if ($checks->isEmpty())
            <p class="empty">No checks recorded yet. The next scheduler tick will run one.</p>
        @else
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Time</th>
                            <th scope="col">Result</th>
                            <th scope="col">HTTP</th>
                            <th scope="col">Response</th>
                            <th scope="col">Error</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($checks as $check)
                            <tr>
                                <td class="mono">{{ $check->checked_at->format('Y-m-d H:i:s') }}</td>
                                <td>
                                    <span class="badge {{ $check->ok ? 'badge-up' : 'badge-down' }}">{{ $check->ok ? 'OK' : 'Fail' }}</span>
                                </td>
                                <td class="mono">{{ $check->http_status ?? '—' }}</td>
                                <td class="mono">{{ $check->response_time_ms !== null ? $check->response_time_ms.' ms' : '—' }}</td>
                                <td class="mono">{{ $check->error ?? '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
