<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('source_status')->nullable()->after('source_character_count'); // extracting|ready|failed
            $table->text('source_error')->nullable()->after('source_status');
            $table->string('source_temp_path')->nullable()->after('source_error');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['source_status', 'source_error', 'source_temp_path']);
        });
    }
};
