<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->unsignedInteger('bonus_modules')->default(0)->after('modules_rolled_over');
        });

        DB::table('plans')->where('id', 'one_time')->update(['name' => 'One Training Module']);
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('bonus_modules');
        });

        DB::table('plans')->where('id', 'one_time')->update(['name' => 'One-time']);
    }
};
