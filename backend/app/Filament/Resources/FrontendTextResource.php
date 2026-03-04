<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FrontendTextResource\Pages;
use App\Models\FrontendText;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Placeholder;
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
use Illuminate\Support\HtmlString;
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
                'auth.welcome_kicker' => 'auth.welcome_kicker',
                'auth.title' => 'auth.title',
                'auth.subtitle' => 'auth.subtitle',
                'auth.email_label' => 'auth.email_label',
                'auth.password_label' => 'auth.password_label',
                'auth.signing_in_button' => 'auth.signing_in_button',
                'auth.submit_button' => 'auth.submit_button',
                'auth.signup_prompt' => 'auth.signup_prompt',
                'auth.signup_cta' => 'auth.signup_cta',
                'auth.divider_text' => 'auth.divider_text',
                'auth.social_prompt' => 'auth.social_prompt',
                'auth.promo_brand' => 'auth.promo_brand',
                'auth.promo_body' => 'auth.promo_body',
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

    protected static function previewHotspots(): array
    {
        return [
            'auth.login' => [
                'auth.welcome_kicker' => ['top' => 9, 'left' => 8, 'width' => 36, 'height' => 8],
                'auth.title' => ['top' => 17, 'left' => 8, 'width' => 40, 'height' => 11],
                'auth.subtitle' => ['top' => 29, 'left' => 8, 'width' => 40, 'height' => 11],
                'auth.email_label' => ['top' => 42, 'left' => 8, 'width' => 40, 'height' => 8],
                'auth.password_label' => ['top' => 52, 'left' => 8, 'width' => 40, 'height' => 8],
                'auth.submit_button' => ['top' => 62, 'left' => 8, 'width' => 40, 'height' => 9],
                'auth.signing_in_button' => ['top' => 62, 'left' => 8, 'width' => 40, 'height' => 9],
                'auth.signup_prompt' => ['top' => 73, 'left' => 8, 'width' => 40, 'height' => 7],
                'auth.signup_cta' => ['top' => 73, 'left' => 27, 'width' => 21, 'height' => 7],
                'auth.divider_text' => ['top' => 80, 'left' => 24, 'width' => 8, 'height' => 6],
                'auth.social_prompt' => ['top' => 86, 'left' => 13, 'width' => 30, 'height' => 7],
                'auth.promo_brand' => ['top' => 43, 'left' => 62, 'width' => 30, 'height' => 12],
                'auth.promo_body' => ['top' => 56, 'left' => 58, 'width' => 36, 'height' => 16],
            ],
            'projects.list' => [
                'projects.title' => ['top' => 7, 'left' => 6, 'width' => 24, 'height' => 7],
                'projects.subtitle_signed_in_as' => ['top' => 14, 'left' => 6, 'width' => 35, 'height' => 7],
                'projects.refresh_button' => ['top' => 8, 'left' => 72, 'width' => 11, 'height' => 9],
                'projects.refreshing_button' => ['top' => 8, 'left' => 72, 'width' => 11, 'height' => 9],
                'projects.logout_button' => ['top' => 8, 'left' => 84, 'width' => 10, 'height' => 9],
                'projects.create_title' => ['top' => 28, 'left' => 8, 'width' => 30, 'height' => 8],
                'projects.input_label' => ['top' => 38, 'left' => 8, 'width' => 22, 'height' => 7],
                'projects.input_placeholder' => ['top' => 46, 'left' => 8, 'width' => 49, 'height' => 9],
                'projects.create_button' => ['top' => 58, 'left' => 8, 'width' => 24, 'height' => 9],
                'projects.creating_button' => ['top' => 58, 'left' => 8, 'width' => 24, 'height' => 9],
                'projects.recent_title' => ['top' => 75, 'left' => 8, 'width' => 27, 'height' => 8],
                'projects.empty_state' => ['top' => 86, 'left' => 8, 'width' => 28, 'height' => 7],
            ],
        ];
    }

    protected static function renderPagePreview(?string $routeKey, ?string $textKey): HtmlString
    {
        $hotspots = static::previewHotspots();
        $routeHotspots = $routeKey ? ($hotspots[$routeKey] ?? []) : [];
        $selectedHotspot = ($routeKey && $textKey) ? ($routeHotspots[$textKey] ?? null) : null;
        $missingHotspot = $textKey !== null && $textKey !== '' && $selectedHotspot === null;

        return new HtmlString(
            view('filament.forms.frontend-text-preview', [
                'routeKey' => $routeKey,
                'textKey' => $textKey,
                'routeHotspots' => $routeHotspots,
                'selectedHotspot' => $selectedHotspot,
                'missingHotspot' => $missingHotspot,
            ])->render()
        );
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
                    ->live()
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('route_key', $get('route_key'))
                    )
                    ->validationMessages([
                        'unique' => 'This route key and text key combination already exists.',
                    ])
                    ->helperText('Text keys are filtered by the selected route key.'),
                Placeholder::make('page_preview')
                    ->label('Page preview')
                    ->content(fn (Get $get): HtmlString => static::renderPagePreview($get('route_key'), $get('text_key')))
                    ->columnSpanFull(),
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
