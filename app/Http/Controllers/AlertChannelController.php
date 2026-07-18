<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAlertChannelRequest;
use App\Http\Requests\UpdateAlertChannelRequest;
use App\Jobs\SendAlert;
use App\Models\AlertChannel;
use App\Support\Alerts\AlertPayload;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class AlertChannelController extends Controller
{
    public function index(): View
    {
        $channels = AlertChannel::withCount('monitors')->orderBy('name')->get();

        return view('channels.index', compact('channels'));
    }

    public function create(): View
    {
        return view('channels.create', ['channel' => new AlertChannel(['type' => 'webhook'])]);
    }

    public function store(StoreAlertChannelRequest $request): RedirectResponse
    {
        $data = $request->validated();

        AlertChannel::create([
            'type' => $data['type'],
            'name' => $data['name'],
            'config' => $this->configFrom($data['type'], $data),
            'is_enabled' => true,
        ]);

        return redirect()->route('channels.index')->with('status', 'Alert channel created.');
    }

    public function edit(AlertChannel $channel): View
    {
        return view('channels.edit', compact('channel'));
    }

    public function update(UpdateAlertChannelRequest $request, AlertChannel $channel): RedirectResponse
    {
        $data = $request->validated();

        // Keep the existing (masked) config values for any field left blank.
        $config = $channel->config;
        foreach (AlertChannel::configKeys($channel->type) as $key) {
            if (filled($data[$key] ?? null)) {
                $config[$key] = $data[$key];
            }
        }

        $channel->update([
            'name' => $data['name'],
            'is_enabled' => $data['is_enabled'],
            'config' => $config,
        ]);

        return redirect()->route('channels.index')->with('status', 'Alert channel updated.');
    }

    public function destroy(AlertChannel $channel): RedirectResponse
    {
        $channel->delete();

        return redirect()->route('channels.index')->with('status', 'Alert channel deleted.');
    }

    public function test(AlertChannel $channel): RedirectResponse
    {
        if (! $channel->is_enabled) {
            return back()->with('error', 'This channel is disabled. Enable it before sending a test.');
        }

        SendAlert::dispatch($channel->id, AlertPayload::test());

        return back()->with('status', 'Test alert queued. Check the channel.');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function configFrom(string $type, array $data): array
    {
        $config = [];

        foreach (AlertChannel::configKeys($type) as $key) {
            if (filled($data[$key] ?? null)) {
                $config[$key] = $data[$key];
            }
        }

        return $config;
    }
}
