<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->integer('gen_duration_minutes')->nullable()->after('source_status');
            $table->text('gen_learning_objectives')->nullable()->after('gen_duration_minutes');
            $table->integer('gen_assessment_count')->nullable()->after('gen_learning_objectives');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['gen_duration_minutes', 'gen_learning_objectives', 'gen_assessment_count']);
        });
    }
};
