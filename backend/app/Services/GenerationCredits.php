<?php

namespace App\Services;

use App\Jobs\GenerationJob;
use App\Models\GenerationRun;
use App\Models\Module;
use App\Models\Project;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GenerationCredits
{
    public function enqueue(Project $project, array $options): GenerationRun
    {
        return DB::transaction(function () use ($project, $options) {
            User::whereKey($project->user_id)->lockForUpdate()->firstOrFail();
            $project = Project::whereKey($project->id)->lockForUpdate()->firstOrFail();
            abort_if(empty($project->source_text), 422, 'Upload a content source before generating.');
            abort_if(GenerationRun::where('project_id', $project->id)->whereIn('status', ['queued', 'running'])->exists(),
                409, 'Generation is already in progress for this project.');
            $subscription = Subscription::where('user_id', $project->user_id)->where('status', 'active')
                ->with('plan')->latest()->lockForUpdate()->first();
            if (! $subscription || $subscription->modulesAvailable() < 1) {
                abort(response()->json([
                    'message' => 'No generation credits available.',
                    'code' => $subscription ? 'module_limit_reached' : 'no_subscription',
                ], 402));
            }
            $run = GenerationRun::create([
                'id' => (string) Str::uuid(), 'project_id' => $project->id,
                'subscription_id' => $subscription->id, 'status' => 'queued',
            ]);
            $subscription->increment('modules_reserved');
            $module = Module::firstOrNew(['project_id' => $project->id]);
            if (! $module->exists) {
                $module->id = 'mod_'.Str::uuid();
            }
            // Preserve the existing content if regeneration fails.
            $module->status = 'generating';
            $module->generation_error = null;
            $module->save();
            GenerationJob::dispatch(
                projectId: $project->id,
                durationMinutes: $options['duration_minutes'] ?? 20,
                learningObjectives: $options['learning_objectives'] ?? '',
                assessmentCount: $options['assessment_count'] ?? 5,
                generationRunId: $run->id,
            )->onConnection('database')->beforeCommit();

            return $run;
        }, 3);
    }

    public function claim(string $id): bool
    {
        return GenerationRun::whereKey($id)->where('status', 'queued')->update(['status' => 'running']) === 1;
    }

    public function finish(string $id, ?array $moduleJson, ?string $coverImage, ?string $error = null): bool
    {
        return DB::transaction(function () use ($id, $moduleJson, $coverImage, $error) {
            $run = GenerationRun::whereKey($id)->lockForUpdate()->firstOrFail();
            if (! in_array($run->status, ['queued', 'running'], true)) {
                return false;
            }
            $subscription = Subscription::whereKey($run->subscription_id)->lockForUpdate()->firstOrFail();
            $subscription->decrement('modules_reserved');
            if ($moduleJson !== null) {
                $subscription->increment('modules_used');
            }
            $run->update(['status' => $moduleJson !== null ? 'succeeded' : 'failed']);
            $module = Module::where('project_id', $run->project_id)->firstOrFail();
            $update = ['status' => $moduleJson !== null ? 'generated' : ($module->module_json ? 'generated' : 'failed'),
                'generation_error' => $error];
            if ($moduleJson !== null) {
                $update['module_json'] = $moduleJson;
                $update['cover_image_url'] = $coverImage;
            }
            $module->update($update);

            return true;
        }, 3);
    }

    public function reapStuck(string $projectId, \DateTimeInterface $cutoff, string $error): bool
    {
        return DB::transaction(function () use ($projectId, $cutoff, $error) {
            $module = Module::where('project_id', $projectId)->lockForUpdate()->first();
            if (! $module || $module->status !== 'generating' || $module->updated_at >= $cutoff) {
                return false;
            }

            $run = GenerationRun::where('project_id', $projectId)
                ->whereIn('status', ['queued', 'running'])
                ->lockForUpdate()
                ->first();

            if ($run) {
                $subscription = Subscription::whereKey($run->subscription_id)->lockForUpdate()->firstOrFail();
                $subscription->decrement('modules_reserved');
                $run->update(['status' => 'failed']);
            }

            $module->update([
                'status' => $module->module_json ? 'generated' : 'failed',
                'generation_error' => $error,
            ]);

            return true;
        }, 3);
    }
}
