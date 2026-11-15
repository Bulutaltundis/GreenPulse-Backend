<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('observation_analyses', function (Blueprint $table) {
            $table->id();

            $table->foreignId('observation_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('observation_media_id')
                ->unique()
                ->constrained('observation_media')
                ->cascadeOnDelete();

            $table->string('status', 20)->default('queued');

            $table->string('provider', 30)->default('gemini');
            $table->string('model', 100);

            $table->text('summary')->nullable();

            $table->string('detected_category', 50)->nullable();

            $table->boolean('environmental_issue')->nullable();

            $table->decimal('confidence', 5, 2)->nullable();

            $table->json('findings')->nullable();
            $table->json('recommendations')->nullable();

            $table->string('error_code', 100)->nullable();
            $table->text('error_message')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            $table->index(
                ['observation_id', 'status'],
                'observation_analyses_observation_status_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('observation_analyses');
    }
};