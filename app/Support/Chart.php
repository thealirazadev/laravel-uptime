<?php

namespace App\Support;

use App\Models\CheckRollup;
use Illuminate\Support\Collection;

/**
 * Turns rollup rows into small inline SVG strings. Only numbers this class computes
 * are interpolated into the markup, so the output is safe to render unescaped.
 */
class Chart
{
    private const ACCENT = '#1d4ed8';

    private const UP = '#15803d';

    private const DOWN = '#b91c1c';

    private const DOWN_DARK = '#7f1d1d';

    private const MUTED = '#475569';

    private const GRAY = '#cbd5e1';

    private const EMPTY = '<p class="empty">Not enough data yet</p>';

    /**
     * Response-time line over ordered rollups. Failed buckets get a marker under
     * the axis so failures are not conveyed by the line alone.
     *
     * @param  Collection<int, CheckRollup>  $rollups
     */
    public static function responseTime(Collection $rollups, string $window): string
    {
        $points = $rollups->sortBy('period_start')->values();
        $values = $points->pluck('avg_response_time_ms')->filter(fn ($v) => $v !== null);

        if ($values->isEmpty()) {
            return self::EMPTY;
        }

        $min = (int) $values->min();
        $max = (int) $values->max();
        $avg = (int) round($values->avg());

        $width = 600;
        $height = 160;
        $pad = 24;
        $steps = max($points->count() - 1, 1);
        $span = max($max - $min, 1);

        $coords = [];
        $markers = '';

        foreach ($points as $i => $rollup) {
            $x = $pad + ($i / $steps) * ($width - 2 * $pad);

            if ($rollup->avg_response_time_ms !== null) {
                $y = $height - $pad - (($rollup->avg_response_time_ms - $min) / $span) * ($height - 2 * $pad);
                $coords[] = round($x, 1).','.round($y, 1);
            }

            if ((int) $rollup->checks_failed > 0) {
                $markers .= '<circle cx="'.round($x, 1).'" cy="'.($height - $pad + 8).'" r="3" fill="'.self::DOWN.'" />';
            }
        }

        $label = "Average response time over the last {$window}: avg {$avg} ms, range {$min}-{$max} ms";

        return '<svg class="chart" viewBox="0 0 '.$width.' '.$height.'" role="img" aria-label="'.e($label).'">'
            .'<polyline fill="none" stroke="'.self::ACCENT.'" stroke-width="2" '
            .'points="'.implode(' ', $coords).'" />'
            .$markers
            .'<text x="'.$pad.'" y="16" font-size="12" fill="'.self::MUTED.'">'.$min.'-'.$max.' ms</text>'
            .'</svg>';
    }

    /**
     * Segmented uptime bar: one cell per bucket, coloured up/down/no-data, with a
     * dark tick on any bucket that had failures.
     *
     * @param  Collection<int, CheckRollup>  $rollups
     */
    public static function uptimeBar(Collection $rollups, string $window): string
    {
        $points = $rollups->sortBy('period_start')->values();

        if ($points->isEmpty()) {
            return self::EMPTY;
        }

        $width = 600;
        $height = 40;
        $count = $points->count();
        $cell = $width / $count;

        $cells = '';
        $totalChecks = 0;
        $totalFailed = 0;

        foreach ($points as $i => $rollup) {
            $total = (int) $rollup->checks_total;
            $failed = (int) $rollup->checks_failed;
            $totalChecks += $total;
            $totalFailed += $failed;

            $color = $total === 0 ? self::GRAY : ($failed === 0 ? self::UP : self::DOWN);
            $x = round($i * $cell, 1);
            $w = round($cell, 1);

            $cells .= '<rect x="'.$x.'" y="0" width="'.$w.'" height="'.$height.'" fill="'.$color.'" />';

            if ($failed > 0) {
                $cells .= '<rect x="'.$x.'" y="0" width="'.$w.'" height="4" fill="'.self::DOWN_DARK.'" />';
            }
        }

        $percent = $totalChecks > 0 ? round((1 - $totalFailed / $totalChecks) * 100, 2) : null;
        $summary = $percent !== null ? $percent.'%' : 'no data';
        $label = "Uptime over the last {$window}: {$summary} across {$count} buckets";

        return '<svg class="chart" viewBox="0 0 '.$width.' '.$height.'" role="img" aria-label="'.e($label).'">'
            .$cells
            .'</svg>';
    }
}
