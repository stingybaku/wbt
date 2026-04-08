<?php

namespace App\Filament\Resources\ProjectResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class FlagsRelationManager extends RelationManager
{
    protected static string $relationship = 'flags';
    protected static ?string $title = 'Review flags';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'open'     => 'danger',
                        'resolved' => 'success',
                        default    => 'gray',
                    }),

                Tables\Columns\TextColumn::make('reason')
                    ->searchable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('notes')
                    ->placeholder('—')
                    ->color('gray')
                    ->wrap(),

                Tables\Columns\TextColumn::make('target_type')
                    ->label('Type')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('target_id')
                    ->label('Target ID')
                    ->fontFamily('mono')
                    ->color('gray'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Flagged')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(['open' => 'Open', 'resolved' => 'Resolved']),
            ]);
    }
}
