<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PlanResource\Pages;
use App\Models\Plan;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;

class PlanResource extends Resource
{
    protected static ?string $model = Plan::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';
    protected static ?string $navigationGroup = 'Billing';

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                TextInput::make('id')
                    ->required()
                    ->maxLength(64)
                    ->disabled(fn ($record) => $record !== null),

                TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                Select::make('type')
                    ->options(['one_time' => 'One-time', 'subscription' => 'Subscription'])
                    ->required(),

                TextInput::make('price_cents')
                    ->label('Price (cents)')
                    ->numeric()
                    ->required(),

                Select::make('billing_period')
                    ->options(['monthly' => 'Monthly', 'annual' => 'Annual'])
                    ->nullable(),

                TextInput::make('module_limit')
                    ->numeric()
                    ->required(),

                TextInput::make('seat_limit')
                    ->numeric()
                    ->required(),

                Toggle::make('rollover')
                    ->label('Unused modules roll over'),

                Toggle::make('is_active')
                    ->label('Active (visible to users)')
                    ->default(true),
            ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->sortable()->searchable(),
                TextColumn::make('type')->badge()
                    ->color(fn ($state) => $state === 'one_time' ? 'info' : 'success'),
                TextColumn::make('price_cents')
                    ->label('Price')
                    ->formatStateUsing(fn ($state) => '$' . number_format($state / 100, 2)),
                TextColumn::make('module_limit')->label('Modules'),
                TextColumn::make('seat_limit')->label('Seats'),
                ToggleColumn::make('is_active')->label('Active'),
            ])
            ->defaultSort('name')
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListPlans::route('/'),
            'create' => Pages\CreatePlan::route('/create'),
            'edit'   => Pages\EditPlan::route('/{record}/edit'),
        ];
    }
}
