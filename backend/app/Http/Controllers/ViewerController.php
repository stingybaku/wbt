<?php

namespace App\Http\Controllers;

use App\Models\InviteProgress;
use App\Models\ProjectInvite;
use Illuminate\Http\Request;

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

        if (!$invite || !$invite->isActive()) {
            return response()->json(['message' => 'This link is invalid or has expired.'], 403);
        }

        $module = $invite->project->modules->first();

        if (!$module || $module->status !== 'generated') {
            return response()->json(['message' => 'This course is not available yet.'], 404);
        }

        $progress = $invite->progress;

        return response()->json([
            'project_title' => $invite->project->title,
            'module'        => $module->module_json,
            'expires_at'    => $invite->expires_at->toIso8601String(),
            'progress'      => $progress ? [
                'current_screen_index' => $progress->current_screen_index,
                'total_screens'        => $progress->total_screens,
                'completed_at'         => $progress->completed_at?->toIso8601String(),
                'question_answers'     => $progress->question_answers ?? [],
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
            'name'  => 'required|string|max:255',
        ]);

        $invite = ProjectInvite::where('token', $token)->first();

        if (!$invite || !$invite->isActive()) {
            return response()->json(['message' => 'This link is invalid or has expired.'], 403);
        }

        if (strtolower(trim($data['email'])) !== strtolower($invite->email)) {
            return response()->json(['message' => 'The email address does not match this invitation.'], 403);
        }

        InviteProgress::updateOrCreate(
            ['invite_id' => $invite->id],
            ['viewer_name' => trim($data['name'])]
        );

        return response()->json(['ok' => true]);
    }

    /**
     * Update viewer progress (token-gated, no auth required).
     */
    public function updateProgress(Request $request, string $token)
    {
        $invite = ProjectInvite::where('token', $token)->first();

        if (!$invite || !$invite->isActive()) {
            return response()->json(['message' => 'Invalid or expired link.'], 403);
        }

        $data = $request->validate([
            'current_screen_index' => 'required|integer|min:0',
            'total_screens'        => 'required|integer|min:1',
            'question_answers'     => 'sometimes|array',
        ]);

        $isComplete = $data['current_screen_index'] >= $data['total_screens'] - 1;

        $alreadyCompleted = InviteProgress::where('invite_id', $invite->id)
            ->whereNotNull('completed_at')
            ->exists();

        $update = [
            'current_screen_index' => $data['current_screen_index'],
            'total_screens'        => $data['total_screens'],
            'last_active_at'       => now(),
            'completed_at'         => $isComplete
                ? (InviteProgress::where('invite_id', $invite->id)->value('completed_at') ?? now())
                : null,
        ];

        if (array_key_exists('question_answers', $data)) {
            $update['question_answers'] = $data['question_answers'];
        }

        InviteProgress::updateOrCreate(['invite_id' => $invite->id], $update);

        if ($isComplete && !$alreadyCompleted) {
            \App\Jobs\GenerateCertificateJob::dispatch($invite->id);
        }

        return response()->json(['ok' => true]);
    }
}
