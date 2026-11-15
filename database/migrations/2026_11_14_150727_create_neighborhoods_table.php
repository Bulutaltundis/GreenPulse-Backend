<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('neighborhoods', function (Blueprint $table) {
            $table->id();

            $table->string('name', 150);
            $table->string('slug', 180)->unique();

            $table->string('city', 100)->index();
            $table->string('district', 100)->index();

            $table->unsignedInteger('population')->nullable();

            $table->boolean('is_active')->default(true)->index();

            $table->timestamps();

            $table->index(['city', 'district']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
                ALTER TABLE neighborhoods
                ADD COLUMN center geography(Point, 4326)
            ");

            DB::statement("
                ALTER TABLE neighborhoods
                ADD COLUMN boundary geometry(MultiPolygon, 4326)
            ");

            DB::statement("
                CREATE INDEX neighborhoods_center_gist
                ON neighborhoods USING GIST (center)
            ");

            DB::statement("
                CREATE INDEX neighborhoods_boundary_gist
                ON neighborhoods USING GIST (boundary)
            ");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('neighborhoods');
    }
};
