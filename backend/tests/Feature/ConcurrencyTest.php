<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\Support\Fixtures;
use Tests\TestCase;

class ConcurrencyTest extends TestCase
{
    // Committed fixtures are visible to independent database connections/processes.
    use DatabaseMigrations, Fixtures;

    public function test_concurrent_webhook_deliveries_fulfill_once(): void
    {
        $user = User::factory()->create();
        $active = $this->subscription($user);
        $this->subscription($user, ['status' => 'pending', 'stripe_session_id' => 'cs_concurrent']);
        $this->assertSame(['ok', 'ok'], $this->runTogether('payment', ['cs_concurrent', 'cs_concurrent']));
        $this->assertSame(1, $active->fresh()->bonus_modules);
        $this->assertDatabaseCount('transactions', 1);
        $this->assertDatabaseCount('payment_fulfillments', 1);
    }

    public function test_concurrent_projects_cannot_reserve_the_last_credit_twice(): void
    {
        $user = User::factory()->create();
        $sub = $this->subscription($user);
        $projects = [$this->project($user)->id, $this->project($user)->id];
        $results = $this->runTogether('generation', $projects);
        sort($results);
        $this->assertSame(['402', 'ok'], $results);
        $this->assertSame(1, $sub->fresh()->modules_reserved);
        $this->assertDatabaseCount('generation_runs', 1);
        $this->assertDatabaseCount('jobs', 1);
    }

    public function test_simultaneous_first_purchases_share_one_active_subscription(): void
    {
        $user = User::factory()->create();
        $this->subscription($user, ['status' => 'pending', 'stripe_session_id' => 'cs_first_a']);
        $this->subscription($user, ['status' => 'pending', 'stripe_session_id' => 'cs_first_b']);
        $this->assertSame(['ok', 'ok'], $this->runTogether('payment', ['cs_first_a', 'cs_first_b']));
        $this->assertSame(1, \App\Models\Subscription::where('status', 'active')->count());
        $active = \App\Models\Subscription::where('status', 'active')->firstOrFail();
        $this->assertSame(2, $active->modulesAvailable());
        $this->assertDatabaseCount('transactions', 2);
        $this->assertDatabaseCount('payment_fulfillments', 2);
    }

    private function runTogether(string $operation, array $ids): array
    {
        $workers = [];
        $start = (string) (microtime(true) + 1);
        $environment = array_merge(getenv(), ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'testing',
            'APP_KEY' => config('app.key')]);
        foreach ($ids as $id) {
            $process = proc_open([PHP_BINARY, base_path('tests/Support/concurrent-worker.php'), $operation, $id, $start],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path(), $environment);
            $this->assertIsResource($process);
            fclose($pipes[0]);
            $workers[] = [$process, $pipes];
        }
        $results = [];
        foreach ($workers as [$process, $pipes]) {
            $results[] = trim(stream_get_contents($pipes[1]));
            $error = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $this->assertSame(0, proc_close($process), $error);
        }

        return $results;
    }
}
