@php
    [$class, $label] = ! $monitor->is_active
        ? ['badge-warn', 'Paused']
        : match ($monitor->status) {
            'up' => ['badge-up', 'Up'],
            'down' => ['badge-down', 'Down'],
            default => ['badge-neutral', 'Unknown'],
        };
@endphp
<span class="badge {{ $class }}">{{ $label }}</span>
