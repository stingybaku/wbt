<?php

namespace App\Filament\Widgets;

use App\Models\Module;
use App\Models\Project;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AdminStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Users', User::count()),
            Stat::make('Projects', Project::count()),
            Stat::make('Modules', Module::count()),
        ];
    }
}
