<?php

namespace App\Http\Controllers;

use App\Models\Incident;
use App\Models\Monitor;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $monitors = Monitor::orderBy('name')->get();

        $summary = [
            'total' => $monitors->count(),
            'up' => $monitors->where('is_active', true)->where('status', 'up')->count(),
            'down' => $monitors->where('is_active', true)->where('status', 'down')->count(),
            'paused' => $monitors->where('is_active', false)->count(),
        ];

        $openIncidents = Incident::query()
            ->whereNull('closed_at')
            ->with('monitor')
            ->latest('started_at')
            ->get();

        return view('dashboard.index', compact('monitors', 'summary', 'openIncidents'));
    }
}
