<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\Module;
use App\Notifications\GenerationFailedNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Fail modules left in "generating" by a worker that died mid-job.
 *
 * GenerationJob runs with tries=1 and only marks a module failed from inside
 * its own catch block, so a killed worker (deploy, OOM, timeout) leaves the
 * module spinning forever and the author can't tell anything went wrong.
 * The job's own timeout is 11 minutes; the default threshold allows for
 * queue wait on top of that.
 */
class ReapStuckGenerations extends Command
{
    protected $signature = 'generation:reap-stuck
        {--minutes=30 : Fail modules that have been generating for longer than this}
        {--dry-run : List stuck modules without changing them}';

    protected $description = 'Mark modules stuck in "generating" as failed and notify their authors';

    public const ERROR = 'Generation did not finish. Please try again.';

    public function handle(): int
    {
        $minutes = max(15, (int) $this->option('minutes'));
        $cutoff = now()->subMinutes($minutes);

        $stuck = Module::with('project.user')
            ->where('status', 'generating')
            ->where('updated_at', '<', $cutoff)
            ->get();

        foreach ($stuck as $module) {
            if ($this->option('dry-run')) {
                $this->line("{$module->id} (project {$module->project_id}) generating since {$module->updated_at}");
                continue;
            }

            // Conditional update: if the job finished between the query and
            // now, its status is no longer "generating" and this is a no-op.
            $reaped = Module::whereKey($module->id)
                ->where('status', 'generating')
                ->where('updated_at', '<', $cutoff)
                ->update(['status' => 'failed', 'generation_error' => self::ERROR, 'updated_at' => now()]);

            if (! $reaped) {
                continue;
            }

            Event::create([
                'id'         => 'evt_' . Str::uuid(),
                'project_id' => $module->project_id,
                'type'       => 'module.failed',
                'meta'       => ['error' => self::ERROR, 'reaped' => true, 'stuck_since' => $module->updated_at?->toIso8601String()],
            ]);

            try {
                $module->project?->user?->notify(new GenerationFailedNotification($module->project));
            } catch (\Throwable $e) {
                Log::error("ReapStuckGenerations: notifying author of {$module->id} failed: " . $e->getMessage());
            }

            Log::warning("ReapStuckGenerations: failed module {$module->id}, generating since {$module->updated_at}");
        }

        $this->info(($this->option('dry-run') ? 'Found ' : 'Reaped ') . $stuck->count() . ' stuck generation(s).');

        return self::SUCCESS;
    }
}
