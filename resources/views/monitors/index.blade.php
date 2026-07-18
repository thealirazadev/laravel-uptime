@extends('layouts.app')

@section('title', 'Monitors')

@section('content')
    <div class="page-head">
        <h1>Monitors</h1>
        <a class="btn" href="{{ route('monitors.create') }}">Add monitor</a>
    </div>

    @if ($monitors->isEmpty())
        <div class="card empty">
            <p>No monitors yet. Add the first site you want to watch.</p>
            <a class="btn" href="{{ route('monitors.create') }}">Add your first monitor</a>
        </div>
    @else
        <div class="card">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Name</th>
                            <th scope="col">Status</th>
                            <th scope="col">Interval</th>
                            <th scope="col">Last checked</th>
                            <th scope="col"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($monitors as $monitor)
                            <tr>
                                <td>
                                    <a href="{{ route('monitors.show', $monitor) }}">{{ $monitor->name }}</a>
                                </td>
                                <td>@include('partials.monitor-status')</td>
                                <td class="meta">{{ \App\Models\Monitor::intervalOptions()[$monitor->interval_seconds] ?? $monitor->interval_seconds.'s' }}</td>
                                <td class="meta">
                                    {{ $monitor->last_checked_at?->format('Y-m-d H:i') ?? 'Never' }}
                                </td>
                                <td class="actions">
                                    <a class="btn btn-secondary btn-sm" href="{{ route('monitors.edit', $monitor) }}">Edit</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection
