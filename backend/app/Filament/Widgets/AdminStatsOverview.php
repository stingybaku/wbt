<?php

namespace App\Filament\Widgets;

use App\Models\InviteProgress;
use App\Models\Module;
use App\Models\Project;
use App\Models\ProjectInvite;
use App\Models\SourcedImage;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AdminStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $failedCount      = Module::where('status', 'failed')->count();
        $generatingCount  = Module::where('status', 'generating')->count();
        $generatedCount   = Module::where('status', 'generated')->count();

        $activeInvites = ProjectInvite::whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->count();

        $completionsThisWeek = InviteProgress::whereNotNull('completed_at')
            ->where('completed_at', '>=', now()->startOfWeek())
            ->count();

        $completionsTotal = InviteProgress::whereNotNull('completed_at')->count();

        return [
            Stat::make('Users', User::count())
                ->description('Registered accounts')
                ->icon('heroicon-o-users'),

            Stat::make('Projects', Project::count())
                ->description($generatingCount > 0 ? "{$generatingCount} generating now" : "{$generatedCount} with modules")
                ->icon('heroicon-o-folder')
                ->color($generatingCount > 0 ? 'warning' : 'success'),

            Stat::make('Modules generated', $generatedCount)
                ->description($failedCount > 0 ? "{$failedCount} failed" : 'No failures')
                ->icon('heroicon-o-document-text')
                ->color($failedCount > 0 ? 'danger' : 'success'),

            Stat::make('Active invites', $activeInvites)
                ->description('Not expired or revoked')
                ->icon('heroicon-o-envelope'),

            Stat::make('Completions this week', $completionsThisWeek)
                ->description("{$completionsTotal} total all time")
                ->icon('heroicon-o-check-badge')
                ->color('success'),

            Stat::make('Image cache', SourcedImage::count())
                ->description('Sourced images stored')
                ->icon('heroicon-o-photo'),
        ];
    }
}
