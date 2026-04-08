<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProjectResource\Pages;
use App\Filament\Resources\ProjectResource\RelationManagers;
use App\Models\Project;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProjectResource extends Resource
{
    protected static ?string $model = Project::class;

    protected static ?string $navigationIcon  = 'heroicon-o-folder';
    protected static ?string $navigationGroup = 'Content';

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                TextInput::make('title')
                    ->required()
                    ->maxLength(255),
                Toggle::make('rights_attestation')
                    ->required(),
                Select::make('user_id')
                    ->label('Owner')
                    ->relationship('user', 'email')
                    ->searchable()
                    ->preload()
                    ->nullable(),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Project')
                    ->columns(2)
                    ->schema([
                        Infolists\Components\TextEntry::make('title')
                            ->columnSpanFull()
                            ->size('lg')
                            ->weight('bold'),

                        Infolists\Components\TextEntry::make('user.email')
                            ->label('Owner'),

                        Infolists\Components\IconEntry::make('rights_attestation')
                            ->label('Rights attested')
                            ->boolean(),

                        Infolists\Components\TextEntry::make('source_type')
                            ->label('Source type')
                            ->badge()
                            ->placeholder('None'),

                        Infolists\Components\TextEntry::make('source_character_count')
                            ->label('Source length')
                            ->formatStateUsing(fn ($state) => $state ? number_format($state) . ' chars' : '—'),

                        Infolists\Components\TextEntry::make('source_url')
                            ->label('Source URL')
                            ->url(fn ($state) => $state)
                            ->openUrlInNewTab()
                            ->placeholder('—')
                            ->columnSpanFull(),

                        Infolists\Components\TextEntry::make('created_at')
                            ->dateTime(),

                        Infolists\Components\TextEntry::make('updated_at')
                            ->dateTime(),
                    ]),

                Infolists\Components\Section::make('Module')
                    ->columns(2)
                    ->schema([
                        Infolists\Components\ImageEntry::make('module.cover_image_url')
                            ->label('')
                            ->height(160)
                            ->columnSpanFull()
                            ->visible(fn (Project $record) => (bool) $record->module?->cover_image_url),

                        Infolists\Components\TextEntry::make('module.status')
                            ->label('Status')
                            ->badge()
                            ->color(fn ($state) => match ($state) {
                                'generated'  => 'success',
                                'generating' => 'warning',
                                'failed'     => 'danger',
                                default      => 'gray',
                            })
                            ->placeholder('No module yet'),

                        Infolists\Components\TextEntry::make('module_title')
                            ->label('Module title')
                            ->state(fn (Project $record) => $record->module?->module_json['meta']['title'] ?? null)
                            ->placeholder('—'),

                        Infolists\Components\TextEntry::make('screen_count')
                            ->label('Screens')
                            ->state(fn (Project $record): string => ($count = count($record->module?->module_json['screens'] ?? []))
                                ? (string) $count
                                : '—'
                            ),

                        Infolists\Components\TextEntry::make('invite_count')
                            ->label('Invites sent')
                            ->state(fn (Project $record): string => (string) $record->invites()->count()),

                        Infolists\Components\TextEntry::make('completion_count')
                            ->label('Completions')
                            ->state(fn (Project $record): string =>
                                (string) $record->invites()
                                    ->whereHas('progress', fn ($q) => $q->whereNotNull('completed_at'))
                                    ->count()
                            ),

                        Infolists\Components\TextEntry::make('module.generation_error')
                            ->label('Generation error')
                            ->color('danger')
                            ->placeholder('—')
                            ->columnSpanFull()
                            ->visible(fn (Project $record) => $record->module?->status === 'failed'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('module.cover_image_url')
                    ->label('')
                    ->height(40)
                    ->width(72)
                    ->defaultImageUrl(null),

                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('user.email')
                    ->label('Owner')
                    ->sortable(),

                Tables\Columns\TextColumn::make('module.status')
                    ->label('Module')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'generated'  => 'success',
                        'generating' => 'warning',
                        'failed'     => 'danger',
                        default      => 'gray',
                    })
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('invite_count')
                    ->label('Invites')
                    ->state(fn (Project $record): string => (string) $record->invites()->count())
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordUrl(fn (Project $record) => static::getUrl('view', ['record' => $record]))
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\InvitesRelationManager::class,
            RelationManagers\EventsRelationManager::class,
            RelationManagers\FlagsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListProjects::route('/'),
            'create' => Pages\CreateProject::route('/create'),
            'view'   => Pages\ViewProject::route('/{record}'),
            'edit'   => Pages\EditProject::route('/{record}/edit'),
        ];
    }
}
