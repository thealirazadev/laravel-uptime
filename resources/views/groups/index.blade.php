@extends('layouts.app')

@section('title', 'Groups')

@section('content')
    <div class="page-head">
        <h1>Monitor groups</h1>
        <a class="btn" href="{{ route('groups.create') }}">Add group</a>
    </div>

    @if ($groups->isEmpty())
        <div class="card empty">
            <p>No groups yet. Create a group to publish a shared status page for a set of monitors.</p>
            <a class="btn" href="{{ route('groups.create') }}">Add your first group</a>
        </div>
    @else
        <div class="card">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Name</th>
                            <th scope="col">Slug</th>
                            <th scope="col">Visibility</th>
                            <th scope="col">Monitors</th>
                            <th scope="col">Status page</th>
                            <th scope="col"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($groups as $group)
                            <tr>
                                <td>{{ $group->name }}</td>
                                <td class="mono">{{ $group->slug }}</td>
                                <td>
                                    <span class="badge {{ $group->is_public ? 'badge-up' : 'badge-neutral' }}">
                                        {{ $group->is_public ? 'Public' : 'Private' }}
                                    </span>
                                </td>
                                <td class="meta">{{ $group->monitors_count }}</td>
                                <td>
                                    @if ($group->is_public && Route::has('status.show'))
                                        <a href="{{ route('status.show', $group->slug) }}">View</a>
                                    @else
                                        <span class="meta">—</span>
                                    @endif
                                </td>
                                <td class="actions">
                                    <a class="btn btn-secondary btn-sm" href="{{ route('groups.edit', $group) }}">Edit</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection
