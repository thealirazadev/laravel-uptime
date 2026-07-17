@extends('layouts.app')

@section('title', 'Incident')

@section('content')
    <div class="page-head">
        <h1>
            Incident: {{ $incident->monitor->name }}
            <span class="badge {{ $incident->isOpen() ? 'badge-down' : 'badge-neutral' }}">
                {{ $incident->isOpen() ? 'Open' : 'Resolved' }}
            </span>
        </h1>
        <a href="{{ route('monitors.show', $incident->monitor) }}">View monitor</a>
    </div>

    <div class="card">
        <div class="stat-grid">
            <div class="stat">
                <div class="label">Started</div>
                <div class="value" style="font-size:1rem">{{ $incident->started_at->format('Y-m-d H:i:s') }} UTC</div>
            </div>
            <div class="stat">
                <div class="label">Resolved</div>
                <div class="value" style="font-size:1rem">{{ $incident->closed_at?->format('Y-m-d H:i:s').' UTC' ?? 'Ongoing' }}</div>
            </div>
            @if ($incident->summary)
                <div class="stat">
                    <div class="label">Summary</div>
                    <div class="value mono" style="font-size:0.9rem">{{ $incident->summary }}</div>
                </div>
            @endif
        </div>
    </div>

    <div class="card">
        <h2>Timeline</h2>
        @if ($incident->events->isEmpty())
            <p class="empty">No timeline entries.</p>
        @else
            <ul class="timeline">
                @foreach ($incident->events->sortBy('created_at') as $event)
                    <li>
                        <span class="dot dot-{{ $event->type }}"></span>
                        <time datetime="{{ $event->created_at->toIso8601String() }}">{{ $event->created_at->format('Y-m-d H:i:s') }} UTC</time>
                        {{ $event->message }}
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@endsection
