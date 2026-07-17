@extends('layouts.app')

@section('title', 'Edit alert channel')

@section('content')
    <div class="page-head">
        <h1>Edit alert channel</h1>
    </div>

    <div class="card">
        <form method="POST" action="{{ route('channels.update', $channel) }}">
            @method('PUT')
            @include('channels._form')

            <div class="actions">
                <button type="submit" class="btn">Save changes</button>
                <a class="btn btn-secondary" href="{{ route('channels.index') }}">Cancel</a>
            </div>
        </form>
    </div>

    <div class="card">
        <h2>Delete channel</h2>
        <p class="meta">This removes the channel and detaches it from all monitors.</p>
        <form method="POST" action="{{ route('channels.destroy', $channel) }}"
              data-confirm="Delete this alert channel?">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-danger">Delete channel</button>
        </form>
    </div>
@endsection
