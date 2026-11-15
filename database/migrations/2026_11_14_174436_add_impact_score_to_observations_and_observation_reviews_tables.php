<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('observations', function (Blueprint $table) {
            $table->smallInteger('impact_score')
                ->nullable()
                ->after('reviewed_at');
        });

        Schema::table('observation_reviews', function (Blueprint $table) {
            $table->smallInteger('impact_score')
                ->nullable()
                ->after('new_status');
        });
    }

    public function down(): void
    {
        Schema::table('observation_reviews', function (Blueprint $table) {
            $table->dropColumn('impact_score');
        });

        Schema::table('observations', function (Blueprint $table) {
            $table->dropColumn('impact_score');
        });
    }
};
