@extends('layouts.app')

@section('title', 'Add monitor')

@section('content')
    <div class="page-head">
        <h1>Add monitor</h1>
    </div>

    <div class="card">
        <form method="POST" action="{{ route('monitors.store') }}">
            @include('monitors._form')

            <div class="actions">
                <button type="submit" class="btn">Create monitor</button>
                <a class="btn btn-secondary" href="{{ route('monitors.index') }}">Cancel</a>
            </div>
        </form>
    </div>
@endsection
