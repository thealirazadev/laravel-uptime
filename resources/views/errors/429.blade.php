<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Too many requests</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    <main class="status-page">
        <h1>Too many requests</h1>
        <p>You have made too many requests in a short time. Please wait a moment and try again.</p>
        <p><a href="{{ url('/') }}">Return home</a></p>
    </main>
</body>
</html>
