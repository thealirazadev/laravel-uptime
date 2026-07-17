@extends('layouts.app')

@section('title', 'Add group')

@section('content')
    <div class="page-head">
        <h1>Add group</h1>
    </div>

    <div class="card">
        <form method="POST" action="{{ route('groups.store') }}">
            @include('groups._form')

            <div class="actions">
                <button type="submit" class="btn">Create group</button>
                <a class="btn btn-secondary" href="{{ route('groups.index') }}">Cancel</a>
            </div>
        </form>
    </div>
@endsection
