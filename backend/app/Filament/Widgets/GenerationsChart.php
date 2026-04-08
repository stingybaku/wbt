<?php

namespace App\Filament\Widgets;

use App\Models\Module;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class GenerationsChart extends ChartWidget
{
    protected static ?string $heading = 'Courses generated — last 30 days';
    protected static ?int $sort = 3;

    protected function getData(): array
    {
        $days   = collect(range(29, 0))->map(fn ($i) => now()->subDays($i)->toDateString());
        $counts = Module::where('status', 'generated')
            ->where('updated_at', '>=', now()->subDays(29)->startOfDay())
            ->selectRaw("DATE(updated_at) as day, COUNT(*) as total")
            ->groupBy('day')
            ->pluck('total', 'day');

        return [
            'datasets' => [
                [
                    'label'           => 'Generations',
                    'data'            => $days->map(fn ($d) => $counts->get($d, 0))->values()->toArray(),
                    'borderColor'     => '#f43f23',
                    'backgroundColor' => 'rgba(244, 63, 35, 0.1)',
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
