<?php

namespace Tests\Feature;

use App\Console\Commands\ReapStuckGenerations;
use App\Models\Event;
use App\Models\Module;
use App\Models\Project;
use App\Models\User;
use App\Notifications\GenerationFailedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ReapStuckGenerationsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->user = User::factory()->create();
    }

    private function module(string $id, string $status, int $minutesAgo): Module
    {
        Project::create([
            'id' => "proj_{$id}", 'title' => "Project {$id}", 'rights_attestation' => true, 'user_id' => $this->user->id,
        ]);
        $module = Module::create(['id' => "mod_{$id}", 'project_id' => "proj_{$id}", 'status' => $status]);
        Module::whereKey($module->id)->update(['updated_at' => now()->subMinutes($minutesAgo)]);

        return $module;
    }

    public function test_fails_only_modules_generating_past_the_threshold(): void
    {
        $this->module('stuck', 'generating', 45);
        $this->module('fresh', 'generating', 5);
        $this->module('done', 'generated', 120);

        $this->artisan('generation:reap-stuck')->assertSuccessful();

        $this->assertSame('failed', Module::find('mod_stuck')->status);
        $this->assertSame(ReapStuckGenerations::ERROR, Module::find('mod_stuck')->generation_error);
        $this->assertSame('generating', Module::find('mod_fresh')->status);
        $this->assertSame('generated', Module::find('mod_done')->status);

        Notification::assertSentToTimes($this->user, GenerationFailedNotification::class, 1);
        $event = Event::where('project_id', 'proj_stuck')->where('type', 'module.failed')->sole();
        $this->assertTrue($event->meta['reaped']);
    }

    public function test_dry_run_changes_nothing(): void
    {
        $this->module('stuck', 'generating', 45);

        $this->artisan('generation:reap-stuck --dry-run')
            ->expectsOutputToContain('mod_stuck')
            ->assertSuccessful();

        $this->assertSame('generating', Module::find('mod_stuck')->status);
        Notification::assertNothingSent();
    }

    public function test_threshold_cannot_undercut_the_job_timeout(): void
    {
        $this->module('running', 'generating', 12);

        $this->artisan('generation:reap-stuck --minutes=1')->assertSuccessful();

        $this->assertSame('generating', Module::find('mod_running')->status);
    }

    public function test_is_scheduled(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('generation:reap-stuck')->assertSuccessful();
    }
}
