<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('invite_progress', function (Blueprint $table) {
            $table->json('visited_screens')->nullable()->after('question_answers');
        });
    }

    public function down(): void
    {
        Schema::table('invite_progress', function (Blueprint $table) {
            $table->dropColumn('visited_screens');
        });
    }
};
