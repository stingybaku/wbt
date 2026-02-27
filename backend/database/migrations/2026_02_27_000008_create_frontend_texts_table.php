<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('frontend_texts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('route_key', 120)->index();
            $table->string('text_key', 120);
            $table->text('value');
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['route_key', 'text_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('frontend_texts');
    }
};
