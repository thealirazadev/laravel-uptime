<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMonitorGroupRequest;
use App\Http\Requests\UpdateMonitorGroupRequest;
use App\Models\MonitorGroup;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class MonitorGroupController extends Controller
{
    public function index(): View
    {
        $groups = MonitorGroup::withCount('monitors')->orderBy('name')->get();

        return view('groups.index', compact('groups'));
    }

    public function create(): View
    {
        return view('groups.create', ['group' => new MonitorGroup(['is_public' => true])]);
    }

    public function store(StoreMonitorGroupRequest $request): RedirectResponse
    {
        MonitorGroup::create($request->validated());

        return redirect()->route('groups.index')->with('status', 'Group created.');
    }

    public function edit(MonitorGroup $group): View
    {
        return view('groups.edit', compact('group'));
    }

    public function update(UpdateMonitorGroupRequest $request, MonitorGroup $group): RedirectResponse
    {
        $group->update($request->validated());

        return redirect()->route('groups.index')->with('status', 'Group updated.');
    }

    public function destroy(MonitorGroup $group): RedirectResponse
    {
        // The FK is nullOnDelete, so monitors survive and detach from the group.
        $group->delete();

        return redirect()->route('groups.index')->with('status', 'Group deleted.');
    }
}
