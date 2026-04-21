<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->unsignedBigInteger('user_id');
            $table->string('plan_id');
            $table->string('status');                        // 'active' | 'cancelled' | 'expired' | 'pending'
            $table->string('paypal_order_id')->nullable();   // one-time purchases
            $table->string('paypal_subscription_id')->nullable(); // recurring subscriptions
            $table->unsignedInteger('modules_used')->default(0);
            $table->unsignedInteger('modules_rolled_over')->default(0); // for Growth plan
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('plan_id')->references('id')->on('plans');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
