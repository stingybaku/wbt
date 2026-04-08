<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProjectInviteResource\Pages;
use App\Models\ProjectInvite;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;

class ProjectInviteResource extends Resource
{
    protected static ?string $model = ProjectInvite::class;

    protected static ?string $navigationIcon  = 'heroicon-o-envelope';
    protected static ?string $navigationLabel = 'Invites';
    protected static ?string $navigationGroup = 'Content';
    protected static ?int    $navigationSort  = 3;

    public static function canCreate(): bool { return false; }

    // ─── Infolist (view page) ────────────────────────────────────────────────

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([

                // ── Invite details ───────────────────────────────────────────
                Infolists\Components\Section::make('Invite')
                    ->columns(2)
                    ->schema([
                        Infolists\Components\TextEntry::make('email')
                            ->label('Invited email'),

                        Infolists\Components\TextEntry::make('progress.viewer_name')
                            ->label('Viewer name')
                            ->placeholder('Not started'),

                        Infolists\Components\TextEntry::make('project.title')
                            ->label('Course')
                            ->url(fn (ProjectInvite $record) => $record->project_id
                                ? route('filament.admin.resources.projects.view', ['record' => $record->project_id])
                                : null),

                        Infolists\Components\TextEntry::make('invitedBy.email')
                            ->label('Invited by'),

                        Infolists\Components\TextEntry::make('status')
                            ->badge()
                            ->state(fn (ProjectInvite $record): string => match (true) {
                                (bool) $record->revoked_at              => 'Revoked',
                                $record->expires_at->isPast()           => 'Expired',
                                (bool) $record->progress?->completed_at => 'Completed',
                                $record->progress !== null              => 'In Progress',
                                default                                 => 'Pending',
                            })
                            ->color(fn (string $state): string => match ($state) {
                                'Completed'   => 'success',
                                'In Progress' => 'info',
                                'Pending'     => 'gray',
                                'Expired'     => 'warning',
                                'Revoked'     => 'danger',
                            }),

                        Infolists\Components\TextEntry::make('expires_at')
                            ->label('Expires')
                            ->dateTime('M j, Y'),

                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Invited at')
                            ->dateTime(),

                        Infolists\Components\TextEntry::make('revoked_at')
                            ->label('Revoked at')
                            ->dateTime()
                            ->placeholder('—')
                            ->visible(fn (ProjectInvite $record) => (bool) $record->revoked_at),
                    ]),

                // ── Learning progress ────────────────────────────────────────
                Infolists\Components\Section::make('Progress')
                    ->columns(2)
                    ->visible(fn (ProjectInvite $record) => $record->progress !== null)
                    ->schema([
                        Infolists\Components\TextEntry::make('progress_label')
                            ->label('Screens visited')
                            ->state(function (ProjectInvite $record): string {
                                $p = $record->progress;
                                if (!$p) return '—';
                                $visited = count($p->visited_screens ?? []);
                                return "{$visited} of {$p->total_screens} screens visited";
                            }),

                        Infolists\Components\TextEntry::make('progress.last_active_at')
                            ->label('Last active')
                            ->dateTime(),

                        Infolists\Components\TextEntry::make('progress.completed_at')
                            ->label('Completed at')
                            ->dateTime()
                            ->placeholder('Not yet completed'),

                        Infolists\Components\TextEntry::make('progress.certificate_path')
                            ->label('Certificate')
                            ->placeholder('Not issued')
                            ->formatStateUsing(fn ($state) => $state ? basename($state) : null),
                    ]),

                // ── Question answers ─────────────────────────────────────────
                Infolists\Components\Section::make('Question answers')
                    ->visible(fn (ProjectInvite $record) => !empty($record->progress?->question_answers))
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('answers')
                            ->label('')
                            ->state(fn (ProjectInvite $record): array => self::buildAnswerRows($record))
                            ->schema([
                                Infolists\Components\TextEntry::make('screen_title')
                                    ->label('Screen')
                                    ->weight('medium'),

                                Infolists\Components\TextEntry::make('prompt')
                                    ->label('Question')
                                    ->color('gray'),

                                Infolists\Components\TextEntry::make('selected_labels')
                                    ->label('Answer given'),

                                Infolists\Components\TextEntry::make('result')
                                    ->label('Result')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'Correct'   => 'success',
                                        'Incorrect' => 'danger',
                                        default     => 'gray',
                                    }),
                            ])
                            ->columns(4)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * Build a display-friendly array of answered questions by cross-referencing
     * the stored question_answers with the module JSON to retrieve question text
     * and choice labels.
     */
    private static function buildAnswerRows(ProjectInvite $record): array
    {
        $answers = $record->progress?->question_answers ?? [];
        if (empty($answers)) return [];

        // Load screens from the module JSON for label lookup
        $screens = $record->project?->module?->module_json['screens'] ?? [];
        $screenMap = collect($screens)->keyBy('id');

        $rows = [];
        foreach ($answers as $screenId => $answer) {
            $screen      = $screenMap->get($screenId);
            $interaction = $screen['interactions'][0] ?? null;
            $choiceMap   = collect($interaction['choices'] ?? [])->keyBy('id');

            $selectedLabels = collect($answer['selected'] ?? [])
                ->map(fn ($id) => $choiceMap->get($id)['text'] ?? $id)
                ->implode(', ');

            $rows[] = [
                'screen_title'    => $screen['title'] ?? $screenId,
                'prompt'          => $interaction['prompt'] ?? '—',
                'selected_labels' => $selectedLabels ?: '—',
                'result'          => ($answer['isCorrect'] ?? false) ? 'Correct' : 'Incorrect',
            ];
        }

        return $rows;
    }

    // ─── Table (list page) ───────────────────────────────────────────────────

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('project.title')
                    ->label('Course')
                    ->sortable()
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('invitedBy.email')
                    ->label('Owner')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('email')
                    ->label('Invited email')
                    ->searchable(),

                Tables\Columns\TextColumn::make('progress.viewer_name')
                    ->label('Viewer name')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->state(fn (ProjectInvite $record): string => match (true) {
                        (bool) $record->revoked_at              => 'Revoked',
                        $record->expires_at->isPast()           => 'Expired',
                        (bool) $record->progress?->completed_at => 'Completed',
                        $record->progress !== null              => 'In Progress',
                        default                                 => 'Pending',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'Completed'   => 'success',
                        'In Progress' => 'info',
                        'Pending'     => 'gray',
                        'Expired'     => 'warning',
                        'Revoked'     => 'danger',
                    }),

                Tables\Columns\TextColumn::make('progress_label')
                    ->label('Progress')
                    ->state(function (ProjectInvite $record): string {
                        $p = $record->progress;
                        if (!$p) return '—';
                        if ($p->completed_at) return 'Completed';
                        return "{$p->current_screen_index} / {$p->total_screens}";
                    }),

                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Expires')
                    ->dateTime('M j, Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Invited')
                    ->dateTime('M j, Y')
                    ->sortable(),
            ])
            ->recordUrl(fn (ProjectInvite $record) => static::getUrl('view', ['record' => $record]))
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'active'    => 'Active',
                        'completed' => 'Completed',
                        'expired'   => 'Expired',
                        'revoked'   => 'Revoked',
                    ])
                    ->query(fn ($query, array $data) => match ($data['value']) {
                        'active'    => $query->whereNull('revoked_at')->where('expires_at', '>', now())
                                            ->whereDoesntHave('progress', fn ($q) => $q->whereNotNull('completed_at')),
                        'completed' => $query->whereHas('progress', fn ($q) => $q->whereNotNull('completed_at')),
                        'expired'   => $query->whereNull('revoked_at')->where('expires_at', '<=', now()),
                        'revoked'   => $query->whereNotNull('revoked_at'),
                        default     => $query,
                    }),

                SelectFilter::make('project')
                    ->label('Course')
                    ->relationship('project', 'title')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('invitedBy')
                    ->label('Owner')
                    ->relationship('invitedBy', 'email')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('revoke')
                    ->label('Revoke')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (ProjectInvite $record) => !$record->revoked_at && $record->expires_at->isFuture())
                    ->action(fn (ProjectInvite $record) => $record->update(['revoked_at' => now()])),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProjectInvites::route('/'),
            'view'  => Pages\ViewProjectInvite::route('/{record}'),
        ];
    }
}
