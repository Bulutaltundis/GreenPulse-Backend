<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('observation_media', function (Blueprint $table) {
            $table->id();

            $table->foreignId('observation_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('disk', 50)->default('private');
            $table->string('path', 1024);
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');

            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();

            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['observation_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('observation_media');
    }
};
