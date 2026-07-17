<?php

namespace App\Http\Controllers;

use App\Models\Incident;
use Illuminate\Contracts\View\View;

class IncidentController extends Controller
{
    public function index(): View
    {
        $incidents = Incident::query()
            ->with('monitor')
            // Open incidents first (standard SQL, portable across SQLite/MySQL),
            // then newest by start time.
            ->orderByRaw('closed_at is null desc')
            ->orderByDesc('started_at')
            ->paginate(25);

        return view('incidents.index', compact('incidents'));
    }

    public function show(Incident $incident): View
    {
        $incident->load('monitor', 'events');

        return view('incidents.show', compact('incident'));
    }
}
