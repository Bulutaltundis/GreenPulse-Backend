<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('observation_reviews', function (Blueprint $table) {
            $table->id();

            $table->foreignId('observation_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('moderator_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('previous_status', 20);
            $table->string('new_status', 20);

            $table->text('reason')->nullable();

            $table->timestamps();

            $table->index(['observation_id', 'created_at']);
            $table->index(['moderator_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('observation_reviews');
    }
};
