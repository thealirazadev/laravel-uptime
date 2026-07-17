@extends('layouts.app')

@section('title', 'Alert channels')

@section('content')
    <div class="page-head">
        <h1>Alert channels</h1>
        <a class="btn" href="{{ route('channels.create') }}">Add channel</a>
    </div>

    @if ($channels->isEmpty())
        <div class="card empty">
            <p>No alert channels yet. Add a mail, Slack, or webhook channel to be notified.</p>
            <a class="btn" href="{{ route('channels.create') }}">Add your first channel</a>
        </div>
    @else
        <div class="card">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Name</th>
                            <th scope="col">Type</th>
                            <th scope="col">State</th>
                            <th scope="col">Monitors</th>
                            <th scope="col"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($channels as $channel)
                            <tr>
                                <td>{{ $channel->name }}</td>
                                <td class="mono">{{ ucfirst($channel->type) }}</td>
                                <td>
                                    <span class="badge {{ $channel->is_enabled ? 'badge-up' : 'badge-neutral' }}">
                                        {{ $channel->is_enabled ? 'Enabled' : 'Disabled' }}
                                    </span>
                                </td>
                                <td class="meta">{{ $channel->monitors_count }}</td>
                                <td class="actions">
                                    @if (Route::has('channels.test'))
                                        <form method="POST" action="{{ route('channels.test', $channel) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-secondary btn-sm">Send test</button>
                                        </form>
                                    @endif
                                    <a class="btn btn-secondary btn-sm" href="{{ route('channels.edit', $channel) }}">Edit</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection
