<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ModuleResource\Pages;
use App\Models\Module;
use App\Models\ModuleVersion;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Str;

class ModuleResource extends Resource
{
    protected static ?string $model = Module::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Content';

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                TextInput::make('id')
                    ->disabled()
                    ->dehydrated(false),
                Select::make('project_id')
                    ->label('Project')
                    ->relationship('project', 'title')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('status')
                    ->required(),
                Textarea::make('module_json')
                    ->label('Module JSON')
                    ->rows(18)
                    ->formatStateUsing(fn ($state) => $state ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '')
                    ->dehydrateStateUsing(fn ($state) => $state ? json_decode($state, true) : null)
                    ->helperText('Edit with care: stored as JSON.'),
            ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                TextColumn::make('project.title')->label('Project')->sortable()->searchable(),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Action::make('reset')
                    ->label('Reset Module')
                    ->requiresConfirmation()
                    ->action(function (Module $record) {
                        $nextVersion = (int) $record->versions()->max('version') + 1;
                        ModuleVersion::create([
                            'id' => 'modver_' . Str::uuid()->toString(),
                            'module_id' => $record->id,
                            'version' => $nextVersion,
                            'module_json' => $record->module_json,
                        ]);
                        $record->module_json = null;
                        $record->status = 'draft';
                        $record->save();
                    }),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListModules::route('/'),
            'create' => Pages\CreateModule::route('/create'),
            'edit' => Pages\EditModule::route('/{record}/edit'),
        ];
    }
}
