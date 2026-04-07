<?php

namespace App\Filament\Widgets;

use App\Models\InviteProgress;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class CompletionsChart extends ChartWidget
{
    protected static ?string $heading = 'Course completions — last 30 days';
    protected static ?int $sort = 2;

    protected function getData(): array
    {
        $days   = collect(range(29, 0))->map(fn ($i) => now()->subDays($i)->toDateString());
        $counts = InviteProgress::whereNotNull('completed_at')
            ->where('completed_at', '>=', now()->subDays(29)->startOfDay())
            ->selectRaw("DATE(completed_at) as day, COUNT(*) as total")
            ->groupBy('day')
            ->pluck('total', 'day');

        return [
            'datasets' => [
                [
                    'label'           => 'Completions',
                    'data'            => $days->map(fn ($d) => $counts->get($d, 0))->values()->toArray(),
                    'borderColor'     => '#f59e0b',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.1)',
                    'fill'            => true,
                    'tension'         => 0.3,
                ],
            ],
            'labels' => $days->map(fn ($d) => Carbon::parse($d)->format('M j'))->values()->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
