<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SourcedImageResource\Pages;
use App\Models\SourcedImage;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SourcedImageResource extends Resource
{
    protected static ?string $model = SourcedImage::class;

    protected static ?string $navigationIcon  = 'heroicon-o-photo';
    protected static ?string $navigationGroup = 'Media';
    protected static ?string $navigationLabel = 'Image Library';
    protected static ?string $modelLabel      = 'Cached Image';
    protected static ?string $pluralModelLabel = 'Image Library';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('url')
                    ->label('Preview')
                    ->height(56)
                    ->width(100)
                    ->defaultImageUrl(fn () => null),

                Tables\Columns\TextColumn::make('topic')
                    ->label('Topic / Query')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('keywords')
                    ->label('Keywords')
                    ->formatStateUsing(fn ($state) => is_array($state) ? implode(' · ', $state) : $state)
                    ->wrap()
                    ->color('gray'),

                Tables\Columns\BadgeColumn::make('source')
                    ->colors([
                        'info'    => 'unsplash',
                        'success' => 'pexels',
                    ]),

                Tables\Columns\TextColumn::make('photographer')
                    ->searchable()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Cached')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('source')
                    ->options([
                        'unsplash' => 'Unsplash',
                        'pexels'   => 'Pexels',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->recordUrl(fn (SourcedImage $record) => static::getUrl('view', ['record' => $record]));
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Image')
                    ->schema([
                        Infolists\Components\ImageEntry::make('url')
                            ->label('')
                            ->height(320)
                            ->columnSpanFull(),

                        Infolists\Components\TextEntry::make('topic')
                            ->label('Topic / Query')
                            ->weight('medium'),

                        Infolists\Components\TextEntry::make('source')
                            ->badge()
                            ->color(fn ($state) => match ($state) {
                                'pexels'   => 'success',
                                'unsplash' => 'info',
                                default    => 'gray',
                            }),

                        Infolists\Components\TextEntry::make('photographer'),

                        Infolists\Components\TextEntry::make('license')
                            ->color('gray'),

                        Infolists\Components\TextEntry::make('external_id')
                            ->label('Provider ID')
                            ->color('gray')
                            ->fontFamily('mono'),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make('Search keywords')
                    ->description('These words are used for keyword-overlap cache lookups.')
                    ->schema([
                        Infolists\Components\TextEntry::make('keywords')
                            ->label('')
                            ->formatStateUsing(fn ($state) => is_array($state) ? implode('  ·  ', $state) : $state)
                            ->columnSpanFull(),
                    ]),

                Infolists\Components\Section::make('Metadata')
                    ->schema([
                        Infolists\Components\TextEntry::make('url')
                            ->label('URL')
                            ->url(fn ($state) => $state)
                            ->openUrlInNewTab()
                            ->fontFamily('mono')
                            ->color('primary')
                            ->columnSpanFull(),

                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Cached at')
                            ->dateTime(),

                        Infolists\Components\TextEntry::make('updated_at')
                            ->label('Last updated')
                            ->dateTime(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSourcedImages::route('/'),
            'view'  => Pages\ViewSourcedImage::route('/{record}'),
        ];
    }
}
