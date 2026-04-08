<?php

namespace App\Filament\Widgets;

use App\Models\Module;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LatestGenerationsTable extends BaseWidget
{
    protected static ?string $heading = 'Recent module generations';
    protected static ?int $sort = 3;
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(Module::with('project')->latest()->limit(10))
            ->columns([
                Tables\Columns\TextColumn::make('project.title')
                    ->label('Project')
                    ->searchable()
                    ->url(fn (Module $record) => $record->project_id
                        ? route('filament.admin.resources.projects.edit', ['record' => $record->project_id])
                        : null),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'generated'  => 'success',
                        'generating' => 'warning',
                        'failed'     => 'danger',
                        default      => 'gray',
                    }),

                Tables\Columns\TextColumn::make('generation_error')
                    ->label('Error')
                    ->limit(80)
                    ->color('danger')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last updated')
                    ->since()
                    ->sortable(),
            ])
            ->paginated(false);
    }
}
