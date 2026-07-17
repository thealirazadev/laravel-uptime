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
        </div>
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
