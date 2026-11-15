<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('neighborhoods', function (Blueprint $table) {
            // Bölge türü
            $table->string('area_type', 30)
                ->default('neighborhood')
                ->index();

            // GIS kaynak kimlikleri
            $table->string('source', 80)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->unsignedBigInteger('source_object_id')->nullable();
            $table->string('source_guid', 38)->nullable();
            $table->string('source_district_guid', 38)->nullable();

            // Deprem yönetmeliği dönemlerine göre bina sayıları
            $table->unsignedInteger('buildings_before_1998')->nullable();
            $table->unsignedInteger('buildings_under_1998_code')->nullable();
            $table->unsignedInteger('buildings_before_2007')->nullable();
            $table->unsignedInteger('buildings_under_2007_code')->nullable();
            $table->unsignedInteger('buildings_under_2018_code')->nullable();
            $table->unsignedInteger('total_buildings')->nullable();

            // Aynı kaynak kaydının tekrar eklenmesini engelle
            $table->unique(
                ['source', 'source_id'],
                'neighborhoods_source_record_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('neighborhoods', function (Blueprint $table) {
            $table->dropUnique('neighborhoods_source_record_unique');

            $table->dropIndex(['area_type']);

            $table->dropColumn([
                'area_type',
                'source',
                'source_id',
                'source_object_id',
                'source_guid',
                'source_district_guid',
                'buildings_before_1998',
                'buildings_under_1998_code',
                'buildings_before_2007',
                'buildings_under_2007_code',
                'buildings_under_2018_code',
                'total_buildings',
            ]);
        });
    }
};
