<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invite_progress', function (Blueprint $table) {
            $table->id();
            $table->string('invite_id');
            $table->integer('current_screen_index')->default(0);
            $table->integer('total_screens')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('last_active_at')->nullable();
            $table->timestamps();

            $table->foreign('invite_id')->references('id')->on('project_invites')->cascadeOnDelete();
            $table->unique('invite_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invite_progress');
    }
};
