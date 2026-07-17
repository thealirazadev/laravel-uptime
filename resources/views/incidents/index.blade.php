@extends('layouts.app')

@section('title', 'Incidents')

@section('content')
    <div class="page-head">
        <h1>Incidents</h1>
    </div>

    @if ($incidents->isEmpty())
        <div class="card empty">
            <p>No incidents recorded. Everything has been healthy.</p>
        </div>
    @else
        <div class="card">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Monitor</th>
                            <th scope="col">Status</th>
                            <th scope="col">Started</th>
                            <th scope="col">Resolved</th>
                            <th scope="col">Summary</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($incidents as $incident)
                            <tr @class(['incident-open' => $incident->isOpen()])>
                                <td>
                                    <a href="{{ route('incidents.show', $incident) }}">{{ $incident->monitor->name }}</a>
                                </td>
                                <td>
                                    <span class="badge {{ $incident->isOpen() ? 'badge-down' : 'badge-neutral' }}">
                                        {{ $incident->isOpen() ? 'Open' : 'Resolved' }}
                                    </span>
                                </td>
                                <td class="mono">{{ $incident->started_at->format('Y-m-d H:i') }}</td>
                                <td class="mono">{{ $incident->closed_at?->format('Y-m-d H:i') ?? '—' }}</td>
                                <td class="mono">{{ $incident->summary }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        @if ($incidents->hasPages())
            <nav class="actions" aria-label="Pagination">
                @if (! $incidents->onFirstPage())
                    <a class="btn btn-secondary btn-sm" href="{{ $incidents->previousPageUrl() }}">Previous</a>
                @endif
                <span class="meta">Page {{ $incidents->currentPage() }} of {{ $incidents->lastPage() }}</span>
                @if ($incidents->hasMorePages())
                    <a class="btn btn-secondary btn-sm" href="{{ $incidents->nextPageUrl() }}">Next</a>
                @endif
            </nav>
        @endif
    @endif
@endsection
