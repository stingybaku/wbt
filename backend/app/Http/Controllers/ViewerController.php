<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\InviteProgress;
use App\Models\ProjectInvite;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ViewerController extends Controller
{
    /**
     * Validate a viewer token and return the module JSON for playback.
     * Also returns saved progress so the frontend can resume where the viewer left off.
     */
    public function show(string $token)
    {
        $invite = ProjectInvite::where('token', $token)
            ->with(['project.modules', 'progress'])
            ->first();

        if (! $invite || ! $invite->isActive()) {
            return response()->json(['message' => 'This link is invalid or has expired.'], 403);
        }

        $module = $invite->project->modules->first();

        if (! $module || $module->status !== 'generated') {
            return response()->json(['message' => 'This course is not available yet.'], 404);
        }

        $progress = $invite->progress;

        return response()->json([
            'project_title' => $invite->project->title,
            'module' => $module->module_json,
            'expires_at' => $invite->expires_at->toIso8601String(),
            'progress' => $progress ? [
                'current_screen_index' => $progress->current_screen_index,
                'total_screens' => $progress->total_screens,
                'completed_at' => $progress->completed_at?->toIso8601String(),
                'question_answers' => $progress->question_answers ?? [],
                'visited_screens' => $progress->visited_screens ?? [],
            ] : null,
        ]);
    }

    /**
     * Verify the viewer's email against the invite before granting access.
     */
    public function verify(Request $request, string $token)
    {
        $data = $request->validate([
            'email' => 'required|email',
            'name' => 'required|string|max:255',
        ]);

        $invite = ProjectInvite::where('token', $token)->first();

        if (! $invite || ! $invite->isActive()) {
            return response()->json(['message' => 'This link is invalid or has expired.'], 403);
        }

        if (strtolower(trim($data['email'])) !== strtolower($invite->email)) {
            return response()->json(['message' => 'The email address does not match this invitation.'], 403);
        }

        $alreadyStarted = InviteProgress::where('invite_id', $invite->id)->exists();

        InviteProgress::updateOrCreate(
            ['invite_id' => $invite->id],
            ['viewer_name' => trim($data['name'])]
        );

        if (! $alreadyStarted) {
            Event::create([
                'id' => 'evt_'.Str::uuid(),
                'project_id' => $invite->project_id,
                'type' => 'viewer.started',
                'meta' => ['email' => $invite->email, 'viewer_name' => trim($data['name'])],
            ]);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Update viewer progress (token-gated, no auth required).
     */
    public function updateProgress(Request $request, string $token)
    {
        $data = $request->validate([
            'current_screen_index' => 'required|integer|min:0',
            'total_screens' => 'sometimes|integer|min:1',
            'question_answers' => 'sometimes|array|max:200',
            'question_answers.*' => 'array',
            'visited_screens' => 'sometimes|array|max:200',
            'visited_screens.*' => 'integer|min:0',
            'complete' => 'sometimes|boolean',
        ]);

        return \Illuminate\Support\Facades\DB::transaction(function () use ($token, $data) {
            $invite = ProjectInvite::where('token', $token)->lockForUpdate()->first();
            abort_unless($invite && $invite->isActive(), 403, 'Invalid or expired link.');
            $module = $invite->project->modules()->where('status', 'generated')->first();
            abort_unless($module, 404, 'This course is not available yet.');
            $progress = InviteProgress::where('invite_id', $invite->id)->lockForUpdate()->first();
            abort_unless($progress && $progress->viewer_name, 403, 'Verify your invitation before saving progress.');
            $update = app(\App\Services\LearningProgress::class)->update(
                $module->module_json['screens'] ?? [], $progress->visited_screens ?? [],
                $progress->question_answers ?? [], $data
            );
            $newCompletion = $update['complete'] && ! $progress->completed_at;
            unset($update['complete']);
            $progress->update($update + [
                'last_active_at' => now(),
                'completed_at' => $progress->completed_at ?? ($newCompletion ? now() : null),
            ]);
            if ($newCompletion) {
                \App\Jobs\GenerateCertificateJob::dispatch($invite->id)->onConnection('database')->beforeCommit();
                Event::create([
                    'id' => 'evt_'.Str::uuid(), 'project_id' => $invite->project_id,
                    'type' => 'viewer.completed',
                    'meta' => ['email' => $invite->email, 'viewer_name' => $progress->viewer_name],
                ]);
            }

            return response()->json(['ok' => true, 'completed_at' => $progress->completed_at?->toIso8601String()]);
        }, 3);
    }
}
