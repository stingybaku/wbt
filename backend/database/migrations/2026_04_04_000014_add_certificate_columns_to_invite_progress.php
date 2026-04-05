<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invite_progress', function (Blueprint $table) {
            $table->string('viewer_name')->nullable()->after('invite_id');
            $table->string('certificate_path')->nullable()->after('completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('invite_progress', function (Blueprint $table) {
            $table->dropColumn(['viewer_name', 'certificate_path']);
        });
    }
};
