@csrf

@php
    $type = old('type', $channel->type ?? 'webhook');
    $showTypes = $channel->exists ? [$channel->type] : ['mail', 'slack', 'webhook'];
    $secretKeys = \App\Models\AlertChannel::secretKeys($channel->type ?? $type);
@endphp

@if ($channel->exists)
    <div class="field">
        <label>Type</label>
        <p class="mono">{{ ucfirst($channel->type) }}</p>
    </div>
@else
    <div class="field @error('type') field-error @enderror">
        <label for="type">Type</label>
        <select id="type" name="type" data-channel-type>
            <option value="webhook" @selected($type === 'webhook')>Generic webhook</option>
            <option value="slack" @selected($type === 'slack')>Slack incoming webhook</option>
            <option value="mail" @selected($type === 'mail')>Email</option>
        </select>
        @error('type')
            <p class="error-message">{{ $message }}</p>
        @enderror
    </div>
@endif

<div class="field @error('name') field-error @enderror">
    <label for="name">Name</label>
    <input id="name" type="text" name="name" value="{{ old('name', $channel->name) }}" required maxlength="255">
    @error('name')
        <p class="error-message">{{ $message }}</p>
    @enderror
</div>

@if (in_array('mail', $showTypes, true))
    <div class="channel-fields" data-type="mail" @unless($channel->exists || $type === 'mail') hidden @endunless>
        <div class="field @error('to') field-error @enderror">
            <label for="to">Recipient email</label>
            <input id="to" type="email" name="to" value="{{ old('to', $channel->config['to'] ?? '') }}" maxlength="255">
            @error('to')
                <p class="error-message">{{ $message }}</p>
            @enderror
        </div>
    </div>
@endif

@if (in_array('slack', $showTypes, true))
    <div class="channel-fields" data-type="slack" @unless($channel->exists || $type === 'slack') hidden @endunless>
        <div class="field @error('webhook_url') field-error @enderror">
            <label for="webhook_url">Slack webhook URL</label>
            @if (in_array('webhook_url', $secretKeys, true) && $channel->exists)
                <p class="hint">Hidden. Leave blank to keep the current value.</p>
            @endif
            <input id="webhook_url" type="url" name="webhook_url" maxlength="2048"
                   value="{{ old('webhook_url', $channel->exists ? '' : '') }}"
                   placeholder="https://hooks.slack.com/services/...">
            @error('webhook_url')
                <p class="error-message">{{ $message }}</p>
            @enderror
        </div>
    </div>
@endif

@if (in_array('webhook', $showTypes, true))
    <div class="channel-fields" data-type="webhook" @unless($channel->exists || $type === 'webhook') hidden @endunless>
        <div class="field @error('url') field-error @enderror">
            <label for="url">Webhook URL</label>
            @if ($channel->exists)
                <p class="hint">Hidden. Leave blank to keep the current value.</p>
            @endif
            <input id="url" type="url" name="url" maxlength="2048"
                   placeholder="https://example.com/hook">
            @error('url')
                <p class="error-message">{{ $message }}</p>
            @enderror
        </div>
        <div class="field @error('secret') field-error @enderror">
            <label for="secret">Signing secret (optional)</label>
            <p class="hint">If set, requests carry an X-Uptime-Signature HMAC header.@if ($channel->exists) Leave blank to keep the current value.@endif</p>
            <input id="secret" type="text" name="secret" maxlength="255" autocomplete="off">
            @error('secret')
                <p class="error-message">{{ $message }}</p>
            @enderror
        </div>
    </div>
@endif

@if ($channel->exists)
    <div class="checkbox-row">
        <input id="is_enabled" type="checkbox" name="is_enabled" value="1" @checked(old('is_enabled', $channel->is_enabled))>
        <label for="is_enabled">Enabled (uncheck to skip this channel without detaching it)</label>
    </div>
@endif
