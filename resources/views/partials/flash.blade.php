@if (session('status'))
    <div class="flash flash-success" role="status">{{ session('status') }}</div>
@endif

@if (session('error'))
    <div class="flash flash-error" role="alert">{{ session('error') }}</div>
@endif
