<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PostgreSQL does not create an index for a FOREIGN KEY constraint (unlike
 * MySQL), so several columns that every request filters on were only reachable
 * by sequential scan.
 *
 * Each index below is ordered to match the query that actually runs, so it
 * serves both the filter and the sort. Columns already covered by a unique
 * constraint are deliberately skipped: project_invites.project_id leads
 * unique(project_id, email), invite_progress.invite_id is unique, and
 * module_versions.module_id leads unique(module_id, version).
 */
return new class extends Migration
{
    public function up(): void
    {
        // ProjectsController@index: where user_id ... orderByDesc(created_at)
        Schema::table('projects', function (Blueprint $table) {
            $table->index(['user_id', 'created_at'], 'projects_user_id_created_at_index');
        });

        // ModuleController@show plus the editor's generation poll, which hits
        // this every 3 seconds while a module is generating.
        Schema::table('modules', function (Blueprint $table) {
            $table->index('project_id', 'modules_project_id_index');
        });

        // Project event feed, sorted newest first.
        Schema::table('events', function (Blueprint $table) {
            $table->index(['project_id', 'created_at'], 'events_project_id_created_at_index');
        });

        Schema::table('module_flags', function (Blueprint $table) {
            $table->index('project_id', 'module_flags_project_id_index');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            // EnforceQuota runs this on every quota-gated request:
            // where user_id, where status = active, latest()
            $table->index(['user_id', 'status'], 'subscriptions_user_id_status_index');
            $table->index('plan_id', 'subscriptions_plan_id_index');

            // PayPal webhooks resolve a subscription by these references.
            $table->index('paypal_order_id', 'subscriptions_paypal_order_id_index');
            $table->index('paypal_subscription_id', 'subscriptions_paypal_subscription_id_index');
        });

        // BillingController@transactions: where user_id ... orderByDesc(created_at)
        Schema::table('transactions', function (Blueprint $table) {
            $table->index(['user_id', 'created_at'], 'transactions_user_id_created_at_index');
            $table->index('subscription_id', 'transactions_subscription_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex('projects_user_id_created_at_index');
        });

        Schema::table('modules', function (Blueprint $table) {
            $table->dropIndex('modules_project_id_index');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex('events_project_id_created_at_index');
        });

        Schema::table('module_flags', function (Blueprint $table) {
            $table->dropIndex('module_flags_project_id_index');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex('subscriptions_user_id_status_index');
            $table->dropIndex('subscriptions_plan_id_index');
            $table->dropIndex('subscriptions_paypal_order_id_index');
            $table->dropIndex('subscriptions_paypal_subscription_id_index');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('transactions_user_id_created_at_index');
            $table->dropIndex('transactions_subscription_id_index');
        });
    }
};
