<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('stripe_session_id')->nullable()->after('paypal_subscription_id');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->renameColumn('paypal_reference', 'payment_reference');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('stripe_session_id');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->renameColumn('payment_reference', 'paypal_reference');
        });
    }
};
