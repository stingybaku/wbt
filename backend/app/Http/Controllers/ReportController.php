<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectInvite;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function show(Request $request, string $projectId)
    {
        $project = Project::where('id', $projectId)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $module          = $project->modules()->where('status', 'generated')->first();
        $questionScreens = $module ? $this->extractQuestionScreens($module->module_json) : [];
        $totalQuestions  = count($questionScreens);

        // Initialise per-question accumulators keyed by screenId
        $pickCounts      = []; // screenId => [ choiceId => int ]
        $correctCounts   = []; // screenId => int
        $respondentCounts = []; // screenId => int (viewers who submitted that question)
        foreach ($questionScreens as $qs) {
            $pickCounts[$qs['screen_id']]       = array_fill_keys(array_column($qs['choices'], 'id'), 0);
            $correctCounts[$qs['screen_id']]    = 0;
            $respondentCounts[$qs['screen_id']] = 0;
        }

        $invites = ProjectInvite::where('project_id', $project->id)
            ->with('progress')
            ->orderByDesc('created_at')
            ->get();

        $started    = 0;
        $completed  = 0;
        $scoreSums  = 0;
        $scoreCount = 0;

        $viewers = $invites->map(function (ProjectInvite $invite) use (
            $totalQuestions, &$pickCounts, &$correctCounts, &$respondentCounts,
            &$started, &$completed, &$scoreSums, &$scoreCount
        ) {
            $progress = $invite->progress;

            if (!$progress) {
                return [
                    'name'            => null,
                    'email'           => $invite->email,
                    'progress'        => null,
                    'score'           => null,
                    'completed_at'    => null,
                    'last_active_at'  => null,
                    'has_certificate' => false,
                ];
            }

            $started++;
            if ($progress->completed_at) $completed++;

            $answers = $progress->question_answers ?? [];
            $correct = 0;

            foreach ($answers as $screenId => $answer) {
                if (!($answer['submitted'] ?? false)) continue;

                if ($answer['isCorrect'] ?? false) $correct++;

                if (isset($pickCounts[$screenId])) {
                    $respondentCounts[$screenId]++;
                    if ($answer['isCorrect'] ?? false) $correctCounts[$screenId]++;
                    foreach ($answer['selected'] ?? [] as $choiceId) {
                        if (array_key_exists($choiceId, $pickCounts[$screenId])) {
                            $pickCounts[$screenId][$choiceId]++;
                        }
                    }
                }
            }

            if ($totalQuestions > 0) {
                $scoreSums += $correct / $totalQuestions;
                $scoreCount++;
            }

            return [
                'name'            => $progress->viewer_name,
                'email'           => $invite->email,
                'progress'        => $progress->total_screens ? [
                    'current' => $progress->current_screen_index,
                    'total'   => $progress->total_screens,
                ] : null,
                'score'           => $totalQuestions > 0 ? [
                    'correct' => $correct,
                    'total'   => $totalQuestions,
                ] : null,
                'completed_at'    => $progress->completed_at?->toIso8601String(),
                'last_active_at'  => $progress->last_active_at?->toIso8601String(),
                'has_certificate' => (bool) $progress->certificate_path,
            ];
        })->values();

        $questions = array_map(function (array $qs) use ($pickCounts, $correctCounts, $respondentCounts) {
            $screenId    = $qs['screen_id'];
            $respondents = $respondentCounts[$screenId];
            $pctCorrect  = $respondents > 0
                ? round($correctCounts[$screenId] / $respondents, 4)
                : null;

            $choices = array_map(fn($choice) => [
                'id'         => $choice['id'],
                'text'       => $choice['text'],
                'is_correct' => $choice['is_correct'],
                'pick_count' => $pickCounts[$screenId][$choice['id']] ?? 0,
            ], $qs['choices']);

            return [
                'screen_id'    => $screenId,
                'prompt'       => $qs['prompt'],
                'pct_correct'  => $pctCorrect,
                'respondents'  => $respondents,
                'choices'      => $choices,
            ];
        }, $questionScreens);

        return response()->json([
            'summary' => [
                'invited'   => $invites->count(),
                'started'   => $started,
                'completed' => $completed,
                'avg_score' => $scoreCount > 0 ? round($scoreSums / $scoreCount, 4) : null,
            ],
            'viewers'   => $viewers,
            'questions' => $questions,
        ]);
    }

    /**
     * Extract question screens (interactions[0] only, matching player behaviour).
     */
    private function extractQuestionScreens(array $moduleJson): array
    {
        $result = [];

        foreach ($moduleJson['screens'] ?? [] as $screen) {
            if (($screen['type'] ?? '') !== 'question') continue;

            $interaction = $screen['interactions'][0] ?? null;
            if (!$interaction) continue;
            if (!in_array($interaction['kind'] ?? '', ['single_choice', 'multi_choice'])) continue;

            $result[] = [
                'screen_id' => $screen['id'],
                'prompt'    => $interaction['prompt'],
                'choices'   => array_map(fn($c) => [
                    'id'         => $c['id'],
                    'text'       => $c['text'],
                    'is_correct' => $c['is_correct'],
                ], $interaction['choices'] ?? []),
            ];
        }

        return $result;
    }
}
