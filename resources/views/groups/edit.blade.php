@extends('layouts.app')

@section('title', 'Edit group')

@section('content')
    <div class="page-head">
        <h1>Edit group</h1>
    </div>

    <div class="card">
        <form method="POST" action="{{ route('groups.update', $group) }}">
            @method('PUT')
            @include('groups._form')

            <div class="actions">
                <button type="submit" class="btn">Save changes</button>
                <a class="btn btn-secondary" href="{{ route('groups.index') }}">Cancel</a>
            </div>
        </form>
    </div>

    <div class="card">
        <h2>Delete group</h2>
        <p class="meta">Monitors in this group are kept and simply detached from it.</p>
        <form method="POST" action="{{ route('groups.destroy', $group) }}"
              data-confirm="Delete this group? Its monitors will be detached.">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-danger">Delete group</button>
        </form>
    </div>
@endsection
