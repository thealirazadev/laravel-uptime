@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <div class="page-head">
        <h1>Dashboard</h1>
        <a class="btn" href="{{ route('monitors.create') }}">Add monitor</a>
    </div>

    <div class="card">
        <div class="stat-grid">
            <div class="stat">
                <div class="label">Monitors</div>
                <div class="value">{{ $summary['total'] }}</div>
            </div>
            <div class="stat">
                <div class="label">Up</div>
                <div class="value" style="color:var(--up)">{{ $summary['up'] }}</div>
            </div>
            <div class="stat">
                <div class="label">Down</div>
                <div class="value" style="color:var(--down)">{{ $summary['down'] }}</div>
            </div>
            <div class="stat">
                <div class="label">Paused</div>
                <div class="value" style="color:var(--warn)">{{ $summary['paused'] }}</div>
            </div>
        </div>
    </div>

    @if ($openIncidents->isNotEmpty())
        <div class="card">
            <h2>Open incidents</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Monitor</th>
                            <th scope="col">Started</th>
                            <th scope="col">Summary</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($openIncidents as $incident)
                            <tr class="incident-open">
                                <td><a href="{{ route('monitors.show', $incident->monitor) }}">{{ $incident->monitor->name }}</a></td>
                                <td class="mono">{{ $incident->started_at->format('Y-m-d H:i') }} UTC</td>
                                <td class="mono">{{ $incident->summary }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="card">
        <h2>Monitors</h2>
        @if ($monitors->isEmpty())
            <div class="empty">
                <p>No monitors yet. Add the first site you want to watch.</p>
                <a class="btn" href="{{ route('monitors.create') }}">Add your first monitor</a>
            </div>
        @else
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Name</th>
                            <th scope="col">Status</th>
                            <th scope="col">Last checked</th>
                            <th scope="col">Last error</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($monitors as $monitor)
                            <tr>
                                <td><a href="{{ route('monitors.show', $monitor) }}">{{ $monitor->name }}</a></td>
                                <td>@include('partials.monitor-status')</td>
                                <td class="meta">{{ $monitor->last_checked_at?->format('Y-m-d H:i') ?? 'Never' }}</td>
                                <td class="mono meta">{{ $monitor->last_error }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
