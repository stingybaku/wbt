<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->string('id')->primary();            // 'one_time' | 'starter' | 'growth'
            $table->string('name');
            $table->string('type');                     // 'one_time' | 'subscription'
            $table->unsignedInteger('price_cents');     // in USD cents
            $table->string('billing_period')->nullable(); // 'annual' | 'monthly' | null
            $table->unsignedInteger('module_limit');    // modules per period (or total for one_time)
            $table->unsignedInteger('seat_limit');      // max active learner seats
            $table->boolean('rollover')->default(false);
            $table->string('paypal_plan_id')->nullable(); // set once PayPal plan is created
            $table->timestamps();
        });

        DB::table('plans')->insert([
            [
                'id'             => 'one_time',
                'name'           => 'One-time',
                'type'           => 'one_time',
                'price_cents'    => 3900,
                'billing_period' => null,
                'module_limit'   => 1,
                'seat_limit'     => 10,
                'rollover'       => false,
                'paypal_plan_id' => null,
                'created_at'     => now(),
                'updated_at'     => now(),
            ],
            [
                'id'             => 'starter',
                'name'           => 'Starter',
                'type'           => 'subscription',
                'price_cents'    => 4900,
                'billing_period' => 'annual',
                'module_limit'   => 2,
                'seat_limit'     => 25,
                'rollover'       => false,
                'paypal_plan_id' => null,
                'created_at'     => now(),
                'updated_at'     => now(),
            ],
            [
                'id'             => 'growth',
                'name'           => 'Growth',
                'type'           => 'subscription',
                'price_cents'    => 8900,
                'billing_period' => 'monthly',
                'module_limit'   => 8,
                'seat_limit'     => 100,
                'rollover'       => true,
                'paypal_plan_id' => null,
                'created_at'     => now(),
                'updated_at'     => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
