<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eco_score_snapshots', function (Blueprint $table) {
            $table->id();

            $table->foreignId('neighborhood_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->unsignedTinyInteger('score');

            $table->unsignedInteger('observation_count')->default(0);

            $table->decimal('average_impact', 5, 2)->default(0);

            $table->timestamp('calculated_at');

            $table->timestamps();

            $table->index(
                ['neighborhood_id', 'calculated_at'],
                'eco_snapshots_neighborhood_date_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eco_score_snapshots');
    }
};