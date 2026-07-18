<?php

namespace App\Http\Controllers;

use App\Models\Incident;
use App\Models\Monitor;
use App\Models\MonitorGroup;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class StatusPageController extends Controller
{
    public function show(string $slug): View
    {
        $group = $this->findPublicGroup($slug);

        abort_if($group === null, 404);

        return view('status.show', ['status' => $this->cachedStatusData($group)]);
    }

    public function json(string $slug): JsonResponse
    {
        $group = $this->findPublicGroup($slug);

        if ($group === null) {
            // Identical body for unknown and non-public: existence is not leaked.
            return response()->json([
                'error' => ['code' => 'not_found', 'message' => 'Status page not found.'],
            ], 404);
        }

        return response()->json(['data' => $this->cachedStatusData($group)]);
    }

    protected function findPublicGroup(string $slug): ?MonitorGroup
    {
        return MonitorGroup::query()->where('slug', $slug)->where('is_public', true)->first();
    }

    /**
     * @return array<string, mixed>
     */
    protected function cachedStatusData(MonitorGroup $group): array
    {
        // ~60 s cache shared by the HTML page and the JSON twin.
        return Cache::remember("status_page:{$group->id}", 60, fn () => $this->statusData($group));
    }

    /**
     * The status payload shared by the HTML page and the JSON twin, so both always
     * agree. No monitor URLs, operator identity, or raw error text is included.
     *
     * @return array<string, mixed>
     */
    protected function statusData(MonitorGroup $group): array
    {
        $generatedAt = now();

        $monitors = $group->monitors()->where('is_active', true)->orderBy('name')->get();

        return [
            'group' => ['name' => $group->name, 'slug' => $group->slug],
            'generated_at' => $generatedAt->toIso8601ZuluString(),
            'overall' => $this->overall($monitors),
            'monitors' => $monitors->map(fn (Monitor $monitor) => [
                'name' => $monitor->name,
                'status' => $monitor->status,
                'last_checked_at' => $monitor->last_checked_at?->toIso8601ZuluString(),
                'uptime' => [
                    'day' => $monitor->uptimePercentage('day'),
                    'week' => $monitor->uptimePercentage('week'),
                    'month' => $monitor->uptimePercentage('month'),
                ],
                'avg_response_time_ms' => ['day' => $monitor->avgResponseTimeDay()],
            ])->values()->all(),
            'incidents' => $this->recentIncidents($monitors, $generatedAt),
        ];
    }

    /**
     * @param  Collection<int, Monitor>  $monitors
     */
    protected function overall(Collection $monitors): string
    {
        if ($monitors->contains(fn (Monitor $monitor) => $monitor->status === 'down')) {
            return 'down';
        }

        if ($monitors->isNotEmpty() && $monitors->every(fn (Monitor $monitor) => $monitor->status === 'up')) {
            return 'operational';
        }

        return 'unknown';
    }

    /**
     * @param  Collection<int, Monitor>  $monitors
     * @return array<int, array<string, mixed>>
     */
    protected function recentIncidents(Collection $monitors, Carbon $generatedAt): array
    {
        return Incident::query()
            ->whereIn('monitor_id', $monitors->pluck('id'))
            ->where('started_at', '>=', $generatedAt->copy()->subDays(14))
            ->with('monitor')
            ->get()
            // Open incidents first, then newest by start time.
            ->sortByDesc(fn (Incident $incident) => [
                $incident->closed_at === null ? 1 : 0,
                $incident->started_at->getTimestamp(),
            ])
            ->map(fn (Incident $incident) => [
                'monitor' => $incident->monitor->name,
                'status' => $incident->closed_at === null ? 'open' : 'resolved',
                'started_at' => $incident->started_at->toIso8601ZuluString(),
                'closed_at' => $incident->closed_at?->toIso8601ZuluString(),
                'duration_seconds' => $incident->closed_at
                    ? $incident->started_at->diffInSeconds($incident->closed_at)
                    : $incident->started_at->diffInSeconds($generatedAt),
            ])
            ->values()
            ->all();
    }
}
