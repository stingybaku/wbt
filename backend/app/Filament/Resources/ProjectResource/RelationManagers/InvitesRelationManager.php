<?php

namespace App\Filament\Resources\ProjectResource\RelationManagers;

use App\Models\ProjectInvite;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class InvitesRelationManager extends RelationManager
{
    protected static string $relationship = 'invites';
    protected static ?string $title = 'Invites';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),

                Tables\Columns\TextColumn::make('progress.viewer_name')
                    ->label('Viewer name')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->state(fn (ProjectInvite $record): string => match (true) {
                        (bool) $record->revoked_at                         => 'Revoked',
                        $record->expires_at->isPast()                      => 'Expired',
                        (bool) $record->progress?->completed_at            => 'Completed',
                        $record->progress !== null                          => 'In Progress',
                        default                                             => 'Pending',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'Completed'   => 'success',
                        'In Progress' => 'info',
                        'Pending'     => 'gray',
                        'Expired'     => 'warning',
                        'Revoked'     => 'danger',
                    }),

                Tables\Columns\TextColumn::make('progress_label')
                    ->label('Progress')
                    ->state(function (ProjectInvite $record): string {
                        $p = $record->progress;
                        if (!$p) return '—';
                        if ($p->completed_at) return 'Completed ' . $p->completed_at->diffForHumans();
                        return "{$p->current_screen_index} / {$p->total_screens} screens";
                    }),

                Tables\Columns\TextColumn::make('progress.last_active_at')
                    ->label('Last active')
                    ->since()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('expires_at')
                    ->dateTime('M j, Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Invited')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
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
}
