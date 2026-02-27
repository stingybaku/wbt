<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FrontendTextResource\Pages;
use App\Models\FrontendText;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;

class FrontendTextResource extends Resource
{
    protected static ?string $model = FrontendText::class;

    protected static ?string $navigationIcon = 'heroicon-o-language';
    protected static ?string $navigationGroup = 'Content';

    protected static function routeKeyOptions(): array
    {
        return [
            'auth.login' => 'auth.login (Login page)',
            'projects.list' => 'projects.list (Projects page)',
        ];
    }

    protected static function textKeyOptions(): array
    {
        return [
            'auth.login' => [
                'auth.title' => 'auth.title',
                'auth.subtitle' => 'auth.subtitle',
                'auth.email_label' => 'auth.email_label',
                'auth.password_label' => 'auth.password_label',
                'auth.signing_in_button' => 'auth.signing_in_button',
                'auth.submit_button' => 'auth.submit_button',
            ],
            'projects.list' => [
                'projects.title' => 'projects.title',
                'projects.subtitle_signed_in_as' => 'projects.subtitle_signed_in_as',
                'projects.refreshing_button' => 'projects.refreshing_button',
                'projects.refresh_button' => 'projects.refresh_button',
                'projects.logout_button' => 'projects.logout_button',
                'projects.create_title' => 'projects.create_title',
                'projects.input_label' => 'projects.input_label',
                'projects.input_placeholder' => 'projects.input_placeholder',
                'projects.creating_button' => 'projects.creating_button',
                'projects.create_button' => 'projects.create_button',
                'projects.recent_title' => 'projects.recent_title',
                'projects.empty_state' => 'projects.empty_state',
            ],
        ];
    }

    protected static function textKeyOptionsForRoute(?string $routeKey): array
    {
        return static::textKeyOptions()[$routeKey] ?? [];
    }

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Select::make('route_key')
                    ->label('Route key')
                    ->required()
                    ->options(static::routeKeyOptions())
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('text_key', null))
                    ->helperText('Example: projects.list or auth.login'),
                Select::make('text_key')
                    ->label('Text key')
                    ->required()
                    ->options(fn (Get $get): array => static::textKeyOptionsForRoute($get('route_key')))
                    ->searchable()
                    ->preload()
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('route_key', $get('route_key'))
                    )
                    ->validationMessages([
                        'unique' => 'This route key and text key combination already exists.',
                    ])
                    ->helperText('Text keys are filtered by the selected route key.'),
                Textarea::make('value')
                    ->required()
                    ->rows(4),
                TextInput::make('description')
                    ->maxLength(255),
                Toggle::make('is_active')
                    ->default(true),
            ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->orderBy('route_key')->orderBy('text_key'))
            ->columns([
                TextColumn::make('route_key')->searchable()->sortable(),
                TextColumn::make('text_key')->searchable()->sortable(),
                TextColumn::make('value')->limit(80)->wrap(),
                IconColumn::make('is_active')->boolean(),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('route_key')
                    ->options(static::routeKeyOptions()),
                SelectFilter::make('is_active')
                    ->options([
                        '1' => 'Active',
                        '0' => 'Inactive',
                    ]),
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
            'index' => Pages\ListFrontendTexts::route('/'),
            'create' => Pages\CreateFrontendText::route('/create'),
            'edit' => Pages\EditFrontendText::route('/{record}/edit'),
        ];
    }
}
