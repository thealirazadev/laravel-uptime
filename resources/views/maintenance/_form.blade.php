@csrf

@php
    $startsValue = old('starts_at', $window->starts_at?->format('Y-m-d\TH:i'));
    $endsValue = old('ends_at', $window->ends_at?->format('Y-m-d\TH:i'));
    $selectedMonitors = old('monitors', $window->exists ? $window->monitors->pluck('id')->all() : []);
    $selectedGroups = old('groups', $window->exists ? $window->groups->pluck('id')->all() : []);
@endphp

<div class="field @error('title') field-error @enderror">
    <label for="title">Title</label>
    <input id="title" type="text" name="title" value="{{ old('title', $window->title) }}" required maxlength="255"
           aria-describedby="@error('title') title-error @enderror">
    @error('title')
        <p class="error-message" id="title-error">{{ $message }}</p>
    @enderror
</div>

<div class="field @error('starts_at') field-error @enderror">
    <label for="starts_at">Starts at</label>
    <p class="hint" id="starts_at-hint">Times are UTC.</p>
    <input id="starts_at" type="datetime-local" name="starts_at" value="{{ $startsValue }}" required
           aria-describedby="starts_at-hint @error('starts_at') starts_at-error @enderror">
    @error('starts_at')
        <p class="error-message" id="starts_at-error">{{ $message }}</p>
    @enderror
</div>

<div class="field @error('ends_at') field-error @enderror">
    <label for="ends_at">Ends at</label>
    <p class="hint" id="ends_at-hint">Must be after the start. Times are UTC.</p>
    <input id="ends_at" type="datetime-local" name="ends_at" value="{{ $endsValue }}" required
           aria-describedby="ends_at-hint @error('ends_at') ends_at-error @enderror">
    @error('ends_at')
        <p class="error-message" id="ends_at-error">{{ $message }}</p>
    @enderror
</div>

<div class="field @error('note') field-error @enderror">
    <label for="note">Note (optional)</label>
    <p class="hint" id="note-hint">A short note, e.g. an intended recurrence like "every Sunday 02:00-04:00".</p>
    <input id="note" type="text" name="note" value="{{ old('note', $window->note) }}" maxlength="255"
           aria-describedby="note-hint @error('note') note-error @enderror">
    @error('note')
        <p class="error-message" id="note-error">{{ $message }}</p>
    @enderror
</div>

<fieldset class="@error('monitors') field-error @enderror">
    <legend>Monitors</legend>
    <p class="hint" id="monitors-hint">Select at least one monitor or group. Both together are allowed.</p>
    @error('monitors')
        <p class="error-message" id="monitors-error">{{ $message }}</p>
    @enderror
    @if ($monitors->isEmpty())
        <p class="hint">No monitors yet.</p>
    @else
        @foreach ($monitors as $monitor)
            <div class="checkbox-row">
                <input id="monitor_{{ $monitor->id }}" type="checkbox" name="monitors[]" value="{{ $monitor->id }}"
                       @checked(in_array($monitor->id, $selectedMonitors))>
                <label for="monitor_{{ $monitor->id }}">{{ $monitor->name }}</label>
            </div>
        @endforeach
    @endif
</fieldset>

<fieldset>
    <legend>Groups</legend>
    @if ($groups->isEmpty())
        <p class="hint">No groups yet.</p>
    @else
        @foreach ($groups as $group)
            <div class="checkbox-row">
                <input id="group_{{ $group->id }}" type="checkbox" name="groups[]" value="{{ $group->id }}"
                       @checked(in_array($group->id, $selectedGroups))>
                <label for="group_{{ $group->id }}">{{ $group->name }}</label>
            </div>
        @endforeach
    @endif
</fieldset>
