@extends('layouts.app')

@section('title', 'Maintenance')

@section('content')
    <div class="page-head">
        <h1>Maintenance windows</h1>
        <a class="btn" href="{{ route('maintenance-windows.create') }}">Add window</a>
    </div>

    @if ($windows->isEmpty())
        <div class="card empty">
            <p>No maintenance windows yet. Schedule one to pause incidents and alerts for planned work.</p>
            <a class="btn" href="{{ route('maintenance-windows.create') }}">Add your first window</a>
        </div>
    @else
        <div class="card">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Title</th>
                            <th scope="col">Window (UTC)</th>
                            <th scope="col">State</th>
                            <th scope="col">Applies to</th>
                            <th scope="col"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($windows as $window)
                            @php
                                [$stateClass, $stateLabel] = match ($window->state()) {
                                    'active' => ['badge-warn', 'Active'],
                                    'scheduled' => ['badge-neutral', 'Scheduled'],
                                    default => ['badge-neutral', 'Ended'],
                                };
                            @endphp
                            <tr>
                                <td>{{ $window->title }}</td>
                                <td class="meta mono">
                                    <time datetime="{{ $window->starts_at->toIso8601ZuluString() }}">{{ $window->starts_at->format('Y-m-d H:i') }}</time>
                                    to
                                    <time datetime="{{ $window->ends_at->toIso8601ZuluString() }}">{{ $window->ends_at->format('Y-m-d H:i') }}</time>
                                </td>
                                <td><span class="badge {{ $stateClass }}">{{ $stateLabel }}</span></td>
                                <td class="meta">{{ $window->monitors_count }} monitor(s), {{ $window->groups_count }} group(s)</td>
                                <td class="actions">
                                    <a class="btn btn-secondary btn-sm" href="{{ route('maintenance-windows.edit', $window) }}">Edit</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection
