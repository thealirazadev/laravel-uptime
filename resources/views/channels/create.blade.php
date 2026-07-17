@extends('layouts.app')

@section('title', 'Add alert channel')

@section('content')
    <div class="page-head">
        <h1>Add alert channel</h1>
    </div>

    <div class="card">
        <form method="POST" action="{{ route('channels.store') }}">
            @include('channels._form')

            <div class="actions">
                <button type="submit" class="btn">Create channel</button>
                <a class="btn btn-secondary" href="{{ route('channels.index') }}">Cancel</a>
            </div>
        </form>
    </div>

    <script>
        (function () {
            var select = document.querySelector('[data-channel-type]');
            if (!select) return;
            var groups = document.querySelectorAll('.channel-fields');
            function sync() {
                groups.forEach(function (group) {
                    group.hidden = group.getAttribute('data-type') !== select.value;
                });
            }
            select.addEventListener('change', sync);
            sync();
        })();
    </script>
@endsection
