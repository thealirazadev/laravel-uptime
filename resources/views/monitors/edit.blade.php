@extends('layouts.app')

@section('title', 'Edit monitor')

@section('content')
    <div class="page-head">
        <h1>Edit monitor</h1>
        <a href="{{ route('monitors.show', $monitor) }}">Back to monitor</a>
    </div>

    <div class="card">
        <form method="POST" action="{{ route('monitors.update', $monitor) }}">
            @method('PUT')
            @include('monitors._form')

            <div class="actions">
                <button type="submit" class="btn">Save changes</button>
                <a class="btn btn-secondary" href="{{ route('monitors.show', $monitor) }}">Cancel</a>
            </div>
        </form>
    </div>

    <div class="card">
        <h2>Delete monitor</h2>
        <p class="meta">This removes the monitor and all its checks and incidents.</p>
        <form method="POST" action="{{ route('monitors.destroy', $monitor) }}"
              data-confirm="Delete this monitor and all its history?">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-danger">Delete monitor</button>
        </form>
    </div>
@endsection
