@extends('layouts.app')

@section('title', 'Add maintenance window')

@section('content')
    <div class="page-head">
        <h1>Add maintenance window</h1>
    </div>

    <div class="card">
        <form method="POST" action="{{ route('maintenance-windows.store') }}">
            @include('maintenance._form')

            <div class="actions">
                <button type="submit" class="btn">Create window</button>
                <a class="btn btn-secondary" href="{{ route('maintenance-windows.index') }}">Cancel</a>
            </div>
        </form>
    </div>
@endsection
