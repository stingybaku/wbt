<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(\Illuminate\Support\Facades\DB::raw('gen_random_uuid()'));
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('subscription_id')->nullable();
            $table->foreign('subscription_id')->references('id')->on('subscriptions')->nullOnDelete();
            $table->string('plan_name');
            $table->string('plan_type'); // 'one_time' | 'subscription'
            $table->integer('amount_cents');
            $table->string('currency', 3)->default('USD');
            $table->string('paypal_reference')->nullable(); // order_id or subscription_id
            $table->string('status')->default('completed'); // completed | refunded
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
