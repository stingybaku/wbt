<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_fulfillments', function (Blueprint $table) {
            $table->string('session_id')->primary();
            $table->timestamp('fulfilled_at');
        });
        // Preserve historical purchases without deleting or changing credits/transactions.
        DB::table('subscriptions')->whereNotNull('stripe_session_id')->where('status', '!=', 'pending')
            ->orderBy('id')->each(function ($subscription) {
                DB::table('payment_fulfillments')->insertOrIgnore([
                    'session_id' => $subscription->stripe_session_id,
                    'fulfilled_at' => $subscription->updated_at,
                ]);
            });
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->unsignedInteger('modules_reserved')->default(0);
        });
        Schema::create('generation_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('project_id');
            $table->string('subscription_id');
            $table->string('status')->default('queued');
            $table->timestamps();
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->foreign('subscription_id')->references('id')->on('subscriptions');
        });
        DB::statement("CREATE UNIQUE INDEX generation_runs_one_active_project ON generation_runs (project_id) WHERE status IN ('queued', 'running')");
    }

    public function down(): void
    {
        Schema::dropIfExists('generation_runs');
        Schema::table('subscriptions', fn (Blueprint $table) => $table->dropColumn('modules_reserved'));
        Schema::dropIfExists('payment_fulfillments');
    }
};
