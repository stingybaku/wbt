<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ModuleVersionResource\Pages;
use App\Models\ModuleVersion;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ModuleVersionResource extends Resource
{
    protected static ?string $model = ModuleVersion::class;

    protected static ?string $navigationIcon  = 'heroicon-o-archive-box';
    protected static ?string $navigationLabel = 'Module versions';
    protected static ?string $navigationGroup = 'Content';
    protected static ?int    $navigationSort  = 5;

    public static function canCreate(): bool { return false; }

    // ─── Infolist (view page) ────────────────────────────────────────────────

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Version')
                    ->columns(2)
                    ->schema([
                        Infolists\Components\TextEntry::make('module.project.title')
                            ->label('Course')
                            ->weight('bold')
                            ->url(fn (ModuleVersion $record) => $record->module?->project_id
                                ? route('filament.admin.resources.projects.view', ['record' => $record->module->project_id])
                                : null),

                        Infolists\Components\TextEntry::make('version')
                            ->label('Version')
                            ->badge()
                            ->color('gray')
                            ->formatStateUsing(fn ($state) => 'v' . $state),

                        Infolists\Components\TextEntry::make('module_title')
                            ->label('Module title')
                            ->state(fn (ModuleVersion $record) => $record->module_json['meta']['title'] ?? '—'),

                        Infolists\Components\TextEntry::make('screen_count')
                            ->label('Screens')
                            ->state(fn (ModuleVersion $record): string =>
                                (string) count($record->module_json['screens'] ?? [])
                            ),

                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Saved at')
                            ->dateTime(),
                    ]),

                Infolists\Components\Section::make('Screens')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('screens')
                            ->label('')
                            ->state(fn (ModuleVersion $record): array =>
                                collect($record->module_json['screens'] ?? [])
                                    ->map(fn ($s, $i) => [
                                        'index' => $i + 1,
                                        'title' => $s['title'] ?? '—',
                                        'type'  => $s['type'] ?? '—',
                                    ])
                                    ->values()
                                    ->all()
                            )
                            ->schema([
                                Infolists\Components\TextEntry::make('index')
                                    ->label('#')
                                    ->alignCenter(),

                                Infolists\Components\TextEntry::make('title')
                                    ->label('Title')
                                    ->weight('medium'),

                                Infolists\Components\TextEntry::make('type')
                                    ->label('Type')
                                    ->badge()
                                    ->color('gray'),
                            ])
                            ->columns(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    // ─── Table (list page) ───────────────────────────────────────────────────

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('module.project.title')
                    ->label('Course')
                    ->sortable()
                    ->searchable()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('version')
                    ->label('Version')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn ($state) => 'v' . $state)
                    ->sortable(),

                Tables\Columns\TextColumn::make('module_title')
                    ->label('Module title')
                    ->state(fn (ModuleVersion $record) => $record->module_json['meta']['title'] ?? '—')
                    ->color('gray'),

                Tables\Columns\TextColumn::make('screen_count')
                    ->label('Screens')
                    ->state(fn (ModuleVersion $record): string =>
                        (string) count($record->module_json['screens'] ?? [])
                    )
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Saved')
                    ->dateTime('M j, Y H:i')
                    ->sortable(),
            ])
            ->recordUrl(fn (ModuleVersion $record) => static::getUrl('view', ['record' => $record]))
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('project')
                    ->label('Course')
                    ->relationship('module.project', 'title')
                    ->searchable()
                    ->preload(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListModuleVersions::route('/'),
            'view'  => Pages\ViewModuleVersion::route('/{record}'),
        ];
    }
}
