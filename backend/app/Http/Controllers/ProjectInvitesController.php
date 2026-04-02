<?php

namespace App\Http\Controllers;

use App\Mail\ViewerInviteMail;
use App\Models\ProjectInvite;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ProjectInvitesController extends Controller
{
    const MAX_INVITES = 10;

    public function index(Request $request, string $projectId)
    {
        $project = Project::where('id', $projectId)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $invites = ProjectInvite::where('project_id', $project->id)
            ->with('progress')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($invite) => $this->formatInvite($invite));

        return response()->json(['invites' => $invites]);
    }

    public function store(Request $request, string $projectId)
    {
        $project = Project::where('id', $projectId)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $data = $request->validate([
            'email'       => 'required|email',
            'expires_days' => 'required|integer|min:1|max:365',
        ]);

        // Enforce basic licence limit (active, non-revoked invites only)
        $activeCount = ProjectInvite::where('project_id', $project->id)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->count();

        if ($activeCount >= self::MAX_INVITES) {
            return response()->json([
                'message' => 'You have reached the maximum of ' . self::MAX_INVITES . ' active viewers on the basic plan.',
            ], 422);
        }

        // If a previous invite exists for this email on this project, revoke it first
        ProjectInvite::where('project_id', $project->id)
            ->where('email', $data['email'])
            ->update(['revoked_at' => now()]);

        $invite = ProjectInvite::create([
            'id'                 => 'inv_' . Str::uuid()->toString(),
            'project_id'         => $project->id,
            'invited_by_user_id' => $request->user()->id,
            'email'              => $data['email'],
            'token'              => Str::random(48),
            'expires_days'       => $data['expires_days'],
            'expires_at'         => now()->addDays($data['expires_days']),
        ]);

        $invite->load('project.modules');

        Mail::to($data['email'])->send(new ViewerInviteMail($invite));

        return response()->json(['invite' => $this->formatInvite($invite)], 201);
    }

    public function revoke(Request $request, string $projectId, string $inviteId)
    {
        $project = Project::where('id', $projectId)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $invite = ProjectInvite::where('id', $inviteId)
            ->where('project_id', $project->id)
            ->firstOrFail();

        $invite->update(['revoked_at' => now()]);

        return response()->json(['ok' => true]);
    }

    private function formatInvite(ProjectInvite $invite): array
    {
        $progress = $invite->progress;
        return [
            'id'           => $invite->id,
            'email'        => $invite->email,
            'expires_days' => $invite->expires_days,
            'expires_at'   => $invite->expires_at->toIso8601String(),
            'revoked_at'   => $invite->revoked_at?->toIso8601String(),
            'is_active'    => $invite->isActive(),
            'created_at'   => $invite->created_at->toIso8601String(),
            'progress'     => $progress ? [
                'current_screen_index' => $progress->current_screen_index,
                'total_screens'        => $progress->total_screens,
                'percent'              => $progress->total_screens
                    ? round(($progress->current_screen_index / $progress->total_screens) * 100)
                    : 0,
                'completed_at'   => $progress->completed_at?->toIso8601String(),
                'last_active_at' => $progress->last_active_at?->toIso8601String(),
            ] : null,
        ];
    }
}
