<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NotificationResource\Pages;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Notifications\DatabaseNotification;

class NotificationResource extends Resource
{
    protected static ?string $model = DatabaseNotification::class;

    protected static ?string $navigationIcon  = 'heroicon-o-bell';
    protected static ?string $navigationLabel = 'Notifications';
    protected static ?string $navigationGroup = 'Content';
    protected static ?int    $navigationSort  = 4;

    public static function canCreate(): bool { return false; }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('notifiable.email')
                    ->label('User')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('data.type')
                    ->label('Type')
                    ->badge()
                    ->color(fn ($state): string => match ($state) {
                        'generation_complete' => 'success',
                        'generation_failed'   => 'danger',
                        'viewer_completed'    => 'info',
                        default               => 'gray',
                    })
                    ->formatStateUsing(fn ($state): string => match ($state) {
                        'generation_complete' => 'Generation complete',
                        'generation_failed'   => 'Generation failed',
                        'viewer_completed'    => 'Viewer completed',
                        default               => $state ?? '—',
                    }),

                Tables\Columns\TextColumn::make('data.body')
                    ->label('Message')
                    ->wrap()
                    ->color('gray'),

                Tables\Columns\IconColumn::make('read_at')
                    ->label('Read')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-clock')
                    ->trueColor('success')
                    ->falseColor('warning')
                    ->state(fn (DatabaseNotification $record): bool => $record->read_at !== null),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Sent')
                    ->dateTime('M j, Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Type')
                    ->options([
                        'generation_complete' => 'Generation complete',
                        'generation_failed'   => 'Generation failed',
                        'viewer_completed'    => 'Viewer completed',
                    ])
                    ->query(fn ($query, array $data) => $data['value']
                        ? $query->where('data->type', $data['value'])
                        : $query
                    ),

                Tables\Filters\Filter::make('unread')
                    ->label('Unread only')
                    ->query(fn ($query) => $query->whereNull('read_at'))
                    ->toggle(),
            ])
            ->paginated([25, 50, 100]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNotifications::route('/'),
        ];
    }
}
