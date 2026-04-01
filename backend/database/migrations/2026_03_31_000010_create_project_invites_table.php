<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_invites', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('project_id');
            $table->foreignId('invited_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('email');
            $table->string('token', 64)->unique();
            $table->integer('expires_days');
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->unique(['project_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_invites');
    }
};
