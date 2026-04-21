<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sourced_images', function (Blueprint $table) {
            $table->string('stored_path')->nullable()->after('url');
        });
    }

    public function down(): void
    {
        Schema::table('sourced_images', function (Blueprint $table) {
            $table->dropColumn('stored_path');
        });
    }
};
