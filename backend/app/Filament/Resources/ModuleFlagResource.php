<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ModuleFlagResource\Pages;
use App\Models\ModuleFlag;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;

class ModuleFlagResource extends Resource
{
    protected static ?string $model = ModuleFlag::class;

    protected static ?string $navigationIcon = 'heroicon-o-flag';
    protected static ?string $navigationGroup = 'Review';

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                TextInput::make('target_id')->required(),
                Select::make('target_type')
                    ->options([
                        'screen' => 'Screen',
                        'interaction' => 'Interaction'
                    ])
                    ->required(),
                TextInput::make('reason')->required(),
                Textarea::make('notes'),
                Select::make('status')
                    ->options([
                        'open' => 'Open',
                        'resolved' => 'Resolved'
                    ])
                    ->required(),
            ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                TextColumn::make('project.title')->label('Project')->sortable(),
                TextColumn::make('target_type')->badge(),
                TextColumn::make('reason')->searchable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListModuleFlags::route('/'),
            'create' => Pages\CreateModuleFlag::route('/create'),
            'edit' => Pages\EditModuleFlag::route('/{record}/edit'),
        ];
    }
}
