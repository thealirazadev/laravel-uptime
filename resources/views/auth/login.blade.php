@extends('layouts.guest')

@section('title', 'Sign in')

@section('content')
    <div class="auth-card">
        <h1>{{ config('app.name') }}</h1>

        @if ($errors->any())
            <div class="flash flash-error" role="alert">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}">
            @csrf

            <div class="field @error('email') field-error @enderror">
                <label for="email">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}"
                       required autofocus autocomplete="username"
                       @error('email') aria-describedby="email-error" @enderror>
                @error('email')
                    <p class="error-message" id="email-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="field @error('password') field-error @enderror">
                <label for="password">Password</label>
                <input id="password" type="password" name="password" required autocomplete="current-password">
            </div>

            <div class="checkbox-row">
                <input id="remember" type="checkbox" name="remember" value="1">
                <label for="remember">Remember me</label>
            </div>

            <button type="submit" class="btn" style="width:100%">Sign in</button>
        </form>
    </div>
@endsection
