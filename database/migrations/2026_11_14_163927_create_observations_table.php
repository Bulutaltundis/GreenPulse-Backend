<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('observations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('neighborhood_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('category', 50);
            $table->text('description');

            $table->string('status', 20)
                ->default('pending');

            $table->timestamp('observed_at')->nullable();

            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['neighborhood_id', 'status']);
            $table->index(['user_id', 'created_at']);
            $table->index('category');
        });

        // PostGIS konum alanı
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE observations
                 ADD COLUMN location geography(Point, 4326)'
            );

            DB::statement(
                'CREATE INDEX observations_location_gist
                 ON observations USING GIST (location)'
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('observations');
    }
};
