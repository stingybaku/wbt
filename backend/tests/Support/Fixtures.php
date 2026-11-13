<?php

namespace Tests\Support;

use App\Models\InviteProgress;
use App\Models\Module;
use App\Models\Project;
use App\Models\ProjectInvite;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Str;

trait Fixtures
{
    private function project(User $user): Project
    {
        return Project::create(['id' => 'proj_'.Str::uuid(), 'user_id' => $user->id,
            'title' => 'Safety', 'rights_attestation' => true, 'source_text' => 'Training source']);
    }

    private function subscription(User $user, array $extra = []): Subscription
    {
        return Subscription::create($extra + ['id' => 'sub_'.Str::uuid(), 'user_id' => $user->id,
            'plan_id' => 'one_time', 'status' => 'active']);
    }

    private function course(Project $project): Module
    {
        return Module::create(['id' => 'mod_'.Str::uuid(), 'project_id' => $project->id,
            'status' => 'generated', 'module_json' => ['screens' => [
                ['id' => 'intro', 'type' => 'image_text', 'interactions' => []],
                ['id' => 'quiz', 'type' => 'question', 'interactions' => [[
                    'kind' => 'single_choice', 'prompt' => 'Choose the safe action', 'choices' => [
                        ['id' => 'safe', 'text' => 'Safe', 'is_correct' => true],
                        ['id' => 'unsafe', 'text' => 'Unsafe', 'is_correct' => false],
                    ],
                ]]],
                ['id' => 'end', 'type' => 'image_text', 'interactions' => []],
            ]]]);
    }

    private function invite(Project $project): ProjectInvite
    {
        $invite = ProjectInvite::create(['id' => 'inv_'.Str::uuid(), 'project_id' => $project->id,
            'invited_by_user_id' => $project->user_id, 'email' => 'learner@example.com',
            'token' => Str::random(48), 'expires_days' => 7, 'expires_at' => now()->addWeek()]);
        InviteProgress::create(['invite_id' => $invite->id, 'viewer_name' => 'Learner']);

        return $invite;
    }
}
