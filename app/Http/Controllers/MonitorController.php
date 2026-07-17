<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMonitorRequest;
use App\Http\Requests\UpdateMonitorRequest;
use App\Models\Monitor;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class MonitorController extends Controller
{
    public function index(): View
    {
        $monitors = Monitor::orderBy('name')->get();

        return view('monitors.index', compact('monitors'));
    }

    public function create(): View
    {
        return view('monitors.create', ['monitor' => new Monitor]);
    }

    public function store(StoreMonitorRequest $request): RedirectResponse
    {
        $monitor = new Monitor($request->validated());
        // New monitors are due immediately so the next scheduler tick checks them.
        $monitor->next_check_at = now();
        $monitor->save();

        return redirect()
            ->route('monitors.show', $monitor)
            ->with('status', 'Monitor created.');
    }

    public function show(Monitor $monitor): View
    {
        $monitor->load('group');
        $checks = $monitor->checks()->latest('checked_at')->limit(20)->get();
        $incidents = $monitor->incidents()->latest('started_at')->limit(10)->get();

        return view('monitors.show', compact('monitor', 'checks', 'incidents'));
    }

    public function edit(Monitor $monitor): View
    {
        return view('monitors.edit', compact('monitor'));
    }

    public function update(UpdateMonitorRequest $request, Monitor $monitor): RedirectResponse
    {
        $monitor->update($request->validated());

        return redirect()
            ->route('monitors.show', $monitor)
            ->with('status', 'Monitor updated.');
    }

    public function destroy(Monitor $monitor): RedirectResponse
    {
        $monitor->delete();

        return redirect()
            ->route('monitors.index')
            ->with('status', 'Monitor deleted.');
    }
}
