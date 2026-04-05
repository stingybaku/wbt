<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProjectInviteResource\Pages;
use App\Models\ProjectInvite;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;

class ProjectInviteResource extends Resource
{
    protected static ?string $model = ProjectInvite::class;

    protected static ?string $navigationIcon = 'heroicon-o-envelope';
    protected static ?string $navigationLabel = 'Invites';
    protected static ?string $navigationGroup = 'Content';
    protected static ?int $navigationSort = 3;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                TextColumn::make('project.title')
                    ->label('Course')
                    ->sortable()
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('invitedBy.email')
                    ->label('Owner')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('email')
                    ->label('Invited Email')
                    ->searchable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->state(function (ProjectInvite $record): string {
                        if ($record->revoked_at) return 'Revoked';
                        if ($record->expires_at->isPast()) return 'Expired';
                        if ($record->progress?->completed_at) return 'Completed';
                        if ($record->progress) return 'In Progress';
                        return 'Pending';
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'Completed'   => 'success',
                        'In Progress' => 'info',
                        'Pending'     => 'gray',
                        'Expired'     => 'warning',
                        'Revoked'     => 'danger',
                    }),

                TextColumn::make('progress.current_screen_index')
                    ->label('Progress')
                    ->state(function (ProjectInvite $record): string {
                        $p = $record->progress;
                        if (!$p) return '—';
                        if ($p->completed_at) return 'Completed';
                        return "{$p->current_screen_index} / {$p->total_screens}";
                    }),

                TextColumn::make('expires_at')
                    ->label('Expires')
                    ->dateTime('M j, Y')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Invited')
                    ->dateTime('M j, Y')
                    ->sortable(),
            ])
            ->defaultSort('project.title')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'active'    => 'Active',
                        'completed' => 'Completed',
                        'expired'   => 'Expired',
                        'revoked'   => 'Revoked',
                    ])
                    ->query(function ($query, array $data) {
                        return match ($data['value']) {
                            'active'    => $query->whereNull('revoked_at')->where('expires_at', '>', now())->whereDoesntHave('progress', fn ($q) => $q->whereNotNull('completed_at')),
                            'completed' => $query->whereHas('progress', fn ($q) => $q->whereNotNull('completed_at')),
                            'expired'   => $query->whereNull('revoked_at')->where('expires_at', '<=', now()),
                            'revoked'   => $query->whereNotNull('revoked_at'),
                            default     => $query,
                        };
                    }),

                SelectFilter::make('project')
                    ->label('Course')
                    ->relationship('project', 'title')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('invitedBy')
                    ->label('Owner')
                    ->relationship('invitedBy', 'email')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\Action::make('revoke')
                    ->label('Revoke')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (ProjectInvite $record) => !$record->revoked_at && $record->expires_at->isFuture())
                    ->action(fn (ProjectInvite $record) => $record->update(['revoked_at' => now()])),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProjectInvites::route('/'),
        ];
    }
}
