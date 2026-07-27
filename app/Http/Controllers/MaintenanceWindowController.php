<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMaintenanceWindowRequest;
use App\Http\Requests\UpdateMaintenanceWindowRequest;
use App\Models\MaintenanceWindow;
use App\Models\Monitor;
use App\Models\MonitorGroup;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class MaintenanceWindowController extends Controller
{
    public function index(): View
    {
        $windows = MaintenanceWindow::withCount(['monitors', 'groups'])
            ->orderByDesc('starts_at')
            ->get();

        return view('maintenance.index', compact('windows'));
    }

    public function create(): View
    {
        return view('maintenance.create', [
            'window' => new MaintenanceWindow,
            'monitors' => Monitor::orderBy('name')->get(),
            'groups' => MonitorGroup::orderBy('name')->get(),
        ]);
    }

    public function store(StoreMaintenanceWindowRequest $request): RedirectResponse
    {
        $window = MaintenanceWindow::create($request->safe()->only(['title', 'starts_at', 'ends_at', 'note']));
        $window->monitors()->sync($request->validated('monitors', []));
        $window->groups()->sync($request->validated('groups', []));

        return redirect()->route('maintenance-windows.index')->with('status', 'Maintenance window created.');
    }

    public function edit(MaintenanceWindow $window): View
    {
        $window->load('monitors:id', 'groups:id');

        return view('maintenance.edit', [
            'window' => $window,
            'monitors' => Monitor::orderBy('name')->get(),
            'groups' => MonitorGroup::orderBy('name')->get(),
        ]);
    }

    public function update(UpdateMaintenanceWindowRequest $request, MaintenanceWindow $window): RedirectResponse
    {
        $window->update($request->safe()->only(['title', 'starts_at', 'ends_at', 'note']));
        $window->monitors()->sync($request->validated('monitors', []));
        $window->groups()->sync($request->validated('groups', []));

        return redirect()->route('maintenance-windows.index')->with('status', 'Maintenance window updated.');
    }

    public function destroy(MaintenanceWindow $window): RedirectResponse
    {
        $window->delete();

        return redirect()->route('maintenance-windows.index')->with('status', 'Maintenance window deleted.');
    }
}
