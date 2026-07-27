@extends('layouts.app')

@section('title', 'Edit maintenance window')

@section('content')
    <div class="page-head">
        <h1>Edit maintenance window</h1>
    </div>

    <div class="card">
        <form method="POST" action="{{ route('maintenance-windows.update', $window) }}">
            @method('PUT')
            @include('maintenance._form')

            <div class="actions">
                <button type="submit" class="btn">Save changes</button>
                <a class="btn btn-secondary" href="{{ route('maintenance-windows.index') }}">Cancel</a>
            </div>
        </form>
    </div>

    <div class="card">
        <h2>Delete window</h2>
        <p class="meta">Deleting a window immediately resumes normal alerting for its monitors.</p>
        <form method="POST" action="{{ route('maintenance-windows.destroy', $window) }}"
              data-confirm="Delete this maintenance window?">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-danger">Delete window</button>
        </form>
    </div>
@endsection
