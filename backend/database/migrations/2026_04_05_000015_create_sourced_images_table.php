<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sourced_images', function (Blueprint $table) {
            $table->id();
            $table->string('source');              // 'unsplash' | 'pexels'
            $table->text('url');
            $table->string('photographer')->nullable();
            $table->string('license');
            $table->string('topic');              // normalized query string
            $table->json('keywords');             // tokenized array of lowercase words
            $table->string('external_id');        // provider's photo ID
            $table->timestamps();

            $table->unique(['source', 'external_id']);
            $table->index('topic');
            $table->index('source');
        });

        DB::statement('ALTER TABLE sourced_images ADD COLUMN embedding vector(512)');
        DB::statement('CREATE INDEX sourced_images_embedding_idx ON sourced_images USING hnsw (embedding vector_cosine_ops)');
    }

    public function down(): void
    {
        Schema::dropIfExists('sourced_images');
    }
};
