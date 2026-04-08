<?php

namespace App\Filament\Resources\ProjectResource\RelationManagers;

use App\Models\Event;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class EventsRelationManager extends RelationManager
{
    protected static string $relationship = 'events';
    protected static ?string $title = 'Events';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match (true) {
                        str_starts_with($state, 'module')  => 'warning',
                        str_starts_with($state, 'viewer')  => 'info',
                        str_starts_with($state, 'invite')  => 'success',
                        default                             => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string =>
                        str_replace(['.', '_'], ' ', $state)
                    ),

                Tables\Columns\TextColumn::make('summary')
                    ->label('Details')
                    ->state(fn (Event $record): string => self::summariseMeta($record->type, $record->meta ?? []))
                    ->color('gray'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('When')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50]);
    }

    private static function summariseMeta(string $type, array $meta): string
    {
        if (empty($meta)) return '—';

        return match (true) {
            // viewer events: show who and what screen
            str_starts_with($type, 'viewer') => implode(' · ', array_filter([
                isset($meta['email']) ? $meta['email'] : null,
                isset($meta['viewer_name']) ? $meta['viewer_name'] : null,
                isset($meta['screen_index']) ? 'screen ' . $meta['screen_index'] : null,
                isset($meta['completed_at']) ? 'completed' : null,
            ])),

            // module events: show status or error snippet
            str_starts_with($type, 'module') => implode(' · ', array_filter([
                isset($meta['status']) ? 'status: ' . $meta['status'] : null,
                isset($meta['error']) ? 'error: ' . str_limit($meta['error'] ?? '', 60) : null,
                isset($meta['screen_count']) ? $meta['screen_count'] . ' screens' : null,
            ])),

            // invite events: show email
            str_starts_with($type, 'invite') => $meta['email'] ?? '—',

            default => collect($meta)
                ->take(3)
                ->map(fn ($v, $k) => "{$k}: " . (is_scalar($v) ? $v : json_encode($v)))
                ->implode(' · '),
        };
    }
}
