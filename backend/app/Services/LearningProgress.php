<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class LearningProgress
{
    /** Calculate answer correctness using the course, never the client's verdict. */
    public function grade(array $screens, array $answers, bool $strict = true): array
    {
        $byId = array_column($screens, null, 'id');
        $graded = [];
        foreach ($answers as $id => $answer) {
            $screen = $byId[$id] ?? null;
            $interaction = $screen['interactions'][0] ?? [];
            $kind = $interaction['kind'] ?? '';
            $selected = $answer['selected'] ?? null;
            $choices = $interaction['choices'] ?? [];
            $valid = is_array($answer) && is_array($selected) && array_is_list($selected)
                && in_array($kind, ['single_choice', 'multi_choice', 'scenario'], true)
                && count($selected) > 0 && count(array_filter($selected, 'is_string')) === count($selected)
                && count(array_unique($selected)) === count($selected)
                && ! array_diff($selected, array_column($choices, 'id'))
                && ($kind === 'multi_choice' || count($selected) === 1)
                && ($answer['submitted'] ?? null) === true;
            if (! $valid) {
                if ($strict) {
                    throw ValidationException::withMessages(['question_answers' => 'Invalid screen or answer selection.']);
                }

                continue;
            }
            $correctIds = array_column(array_filter($choices, fn ($c) => $c['is_correct'] ?? false), 'id');
            $graded[$id] = [
                'selected' => $selected,
                'submitted' => true,
                'isCorrect' => $kind !== 'scenario' && count($correctIds) === count($selected)
                    && ! array_diff($correctIds, $selected),
            ];
        }

        return $graded;
    }

    public function update(array $screens, array $existingVisited, array $existingAnswers, array $data): array
    {
        $total = count($screens);
        $index = (int) $data['current_screen_index'];
        if ($total < 1 || $index >= $total || (isset($data['total_screens']) && (int) $data['total_screens'] !== $total)) {
            throw ValidationException::withMessages(['current_screen_index' => 'Progress does not match this course.']);
        }
        $visited = array_values(array_unique(array_merge([0], array_filter($existingVisited, fn ($i) => is_int($i) && $i >= 0 && $i < $total))));
        if ($index > max($visited) + 1) {
            throw ValidationException::withMessages(['current_screen_index' => 'Visit the preceding screens first.']);
        }
        $allowed = array_values(array_unique([...$visited, $index]));
        if (array_diff($data['visited_screens'] ?? [], $allowed)) {
            throw ValidationException::withMessages(['visited_screens' => 'Unvisited screens cannot be marked as visited.']);
        }
        $answers = $this->grade($screens, $existingAnswers, false);
        foreach ($this->grade($screens, $data['question_answers'] ?? []) as $id => $answer) {
            $screenIndex = array_search((string) $id, array_map('strval', array_column($screens, 'id')), true);
            if (! in_array($screenIndex, $visited, true)) {
                throw ValidationException::withMessages(['question_answers' => 'Visit a screen before answering it.']);
            }
            // Match the player: the first submitted answer is final.
            $answers[$id] ??= $answer;
        }
        if (! in_array($index, $visited, true) && $index > 0) {
            $previous = $screens[$index - 1];
            if (($previous['type'] ?? '') === 'question' && ! isset($answers[$previous['id']])) {
                throw ValidationException::withMessages(['question_answers' => 'Answer the question before continuing.']);
            }
        }
        $complete = ($data['complete'] ?? false) && $index === $total - 1 && count($allowed) === $total;
        foreach ($screens as $screen) {
            if (($screen['type'] ?? '') === 'question' && ! isset($answers[$screen['id']])) {
                $complete = false;
            }
        }
        if (($data['complete'] ?? false) && ! $complete) {
            throw ValidationException::withMessages(['complete' => 'Visit every screen and submit every question before finishing.']);
        }
        sort($allowed);

        return ['current_screen_index' => $index, 'total_screens' => $total,
            'visited_screens' => $allowed, 'question_answers' => $answers, 'complete' => (bool) $complete];
    }
}
