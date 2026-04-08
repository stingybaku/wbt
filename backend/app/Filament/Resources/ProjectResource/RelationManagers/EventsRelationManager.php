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
                    ->color(fn (string $state): string => match ($state) {
                        'module.generated'  => 'success',
                        'module.failed'     => 'danger',
                        'invite.sent'       => 'info',
                        'invite.revoked'    => 'warning',
                        'viewer.started'    => 'gray',
                        'viewer.completed'  => 'success',
                        default             => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'module.generated'  => 'Module generated',
                        'module.failed'     => 'Module failed',
                        'invite.sent'       => 'Invite sent',
                        'invite.revoked'    => 'Invite revoked',
                        'viewer.started'    => 'Viewer started',
                        'viewer.completed'  => 'Viewer completed',
                        default             => str_replace(['.', '_'], ' ', $state),
                    }),

                Tables\Columns\TextColumn::make('summary')
                    ->label('Details')
                    ->state(fn (Event $record): string => self::summarise($record->type, $record->meta ?? []))
                    ->color('gray'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime('M j, Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50]);
    }

    private static function summarise(string $type, array $meta): string
    {
        return match ($type) {
            'module.generated' => isset($meta['screen_count'])
                ? $meta['screen_count'] . ' screens'
                : '—',

            'module.failed' => isset($meta['error'])
                ? \Illuminate\Support\Str::limit($meta['error'], 80)
                : '—',

            'invite.sent' => implode(' · ', array_filter([
                $meta['email'] ?? null,
                isset($meta['expires_days']) ? 'expires in ' . $meta['expires_days'] . 'd' : null,
            ])),

            'invite.revoked' => $meta['email'] ?? '—',

            'viewer.started', 'viewer.completed' => implode(' · ', array_filter([
                $meta['viewer_name'] ?? null,
                $meta['email'] ?? null,
            ])),

            default => empty($meta) ? '—' : collect($meta)
                ->take(3)
                ->map(fn ($v, $k) => "{$k}: " . (is_scalar($v) ? $v : json_encode($v)))
                ->implode(' · '),
        };
    }
}
