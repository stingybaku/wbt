<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('modules', function (Blueprint $table) {
            $table->text('cover_image_url')->nullable()->change();
            $table->text('generation_error')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('modules', function (Blueprint $table) {
            $table->string('cover_image_url')->nullable()->change();
            $table->string('generation_error')->nullable()->change();
        });
    }
};
