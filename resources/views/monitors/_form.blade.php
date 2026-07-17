@csrf

<div class="field @error('name') field-error @enderror">
    <label for="name">Name</label>
    <input id="name" type="text" name="name" value="{{ old('name', $monitor->name) }}"
           required maxlength="255" @error('name') aria-describedby="name-error" @enderror>
    @error('name')
        <p class="error-message" id="name-error">{{ $message }}</p>
    @enderror
</div>

<div class="field @error('url') field-error @enderror">
    <label for="url">URL</label>
    <p class="hint">Full http:// or https:// address to check.</p>
    <input id="url" type="url" name="url" value="{{ old('url', $monitor->url) }}"
           required maxlength="2048" @error('url') aria-describedby="url-error" @enderror>
    @error('url')
        <p class="error-message" id="url-error">{{ $message }}</p>
    @enderror
</div>

<div class="field @error('interval_seconds') field-error @enderror">
    <label for="interval_seconds">Check interval</label>
    <select id="interval_seconds" name="interval_seconds"
            @error('interval_seconds') aria-describedby="interval-error" @enderror>
        @foreach (\App\Models\Monitor::intervalOptions() as $seconds => $label)
            <option value="{{ $seconds }}"
                @selected((int) old('interval_seconds', $monitor->interval_seconds ?? 300) === $seconds)>
                {{ $label }}
            </option>
        @endforeach
    </select>
    @error('interval_seconds')
        <p class="error-message" id="interval-error">{{ $message }}</p>
    @enderror
</div>

<div class="field @error('timeout_seconds') field-error @enderror">
    <label for="timeout_seconds">Timeout (seconds)</label>
    <input id="timeout_seconds" type="number" name="timeout_seconds" min="1" max="30"
           value="{{ old('timeout_seconds', $monitor->timeout_seconds ?? 10) }}"
           @error('timeout_seconds') aria-describedby="timeout-error" @enderror>
    @error('timeout_seconds')
        <p class="error-message" id="timeout-error">{{ $message }}</p>
    @enderror
</div>

<div class="field @error('expected_status') field-error @enderror">
    <label for="expected_status">Expected HTTP status</label>
    <input id="expected_status" type="number" name="expected_status" min="100" max="599"
           value="{{ old('expected_status', $monitor->expected_status ?? 200) }}"
           @error('expected_status') aria-describedby="status-error" @enderror>
    @error('expected_status')
        <p class="error-message" id="status-error">{{ $message }}</p>
    @enderror
</div>

<div class="field @error('expected_keyword') field-error @enderror">
    <label for="expected_keyword">Expected keyword (optional)</label>
    <p class="hint">If set, the response body must contain this text (case-insensitive).</p>
    <input id="expected_keyword" type="text" name="expected_keyword" maxlength="255"
           value="{{ old('expected_keyword', $monitor->expected_keyword) }}"
           @error('expected_keyword') aria-describedby="keyword-error" @enderror>
    @error('expected_keyword')
        <p class="error-message" id="keyword-error">{{ $message }}</p>
    @enderror
</div>

<div class="field @error('confirmation_threshold') field-error @enderror">
    <label for="confirmation_threshold">Confirmation threshold</label>
    <p class="hint">Consecutive matching checks before status flips (flap suppression).</p>
    <input id="confirmation_threshold" type="number" name="confirmation_threshold" min="1" max="10"
           value="{{ old('confirmation_threshold', $monitor->confirmation_threshold ?? 2) }}"
           @error('confirmation_threshold') aria-describedby="threshold-error" @enderror>
    @error('confirmation_threshold')
        <p class="error-message" id="threshold-error">{{ $message }}</p>
    @enderror
</div>

@isset($channels)
    <fieldset>
        <legend>Alert channels</legend>
        @if ($channels->isEmpty())
            <p class="hint">No channels yet. <a href="{{ route('channels.create') }}">Add a channel</a> to receive alerts for this monitor.</p>
        @else
            @php $selected = old('channels', $monitor->exists ? $monitor->channels->pluck('id')->all() : []); @endphp
            @foreach ($channels as $channel)
                <div class="checkbox-row">
                    <input id="channel_{{ $channel->id }}" type="checkbox" name="channels[]" value="{{ $channel->id }}"
                           @checked(in_array($channel->id, $selected))>
                    <label for="channel_{{ $channel->id }}">
                        {{ $channel->name }}
                        <span class="meta">({{ ucfirst($channel->type) }}{{ $channel->is_enabled ? '' : ', disabled' }})</span>
                    </label>
                </div>
            @endforeach
        @endif
    </fieldset>
@endisset

@if ($monitor->exists)
    <div class="checkbox-row">
        <input id="is_active" type="checkbox" name="is_active" value="1"
               @checked(old('is_active', $monitor->is_active))>
        <label for="is_active">Active (uncheck to pause checks)</label>
    </div>
@endif
