<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ImportAnkaraNeighborhoods extends Command
{
    protected $signature = 'greenpulse:import-ankara-neighborhoods
                            {--dry-run : Verileri indirmeden veritabanına yazmadan kontrol et}';

    protected $description = 'Ankara GIS mahalle ve sanayi bölgesi verilerini PostGIS sistemine aktarır.';

    private const BASE_URL =
        'https://planaski.ankara.bel.tr/webgis/rest/services/deprem/ilceMahalleRapor/MapServer';

    private const SOURCE = 'ankara_bb_deprem_gis';

    private const PAGE_SIZE = 250;

    public function handle(): int
    {
        try {
            $this->info('GreenPulse Ankara GIS import başlatılıyor...');

            if (DB::getDriverName() !== 'pgsql') {
                throw new RuntimeException('Bu import PostgreSQL gerektirir.');
            }

            $districts = $this->fetchDistricts();

            $this->info(count($districts) . ' ilçe eşleştirildi.');

            $features = $this->fetchNeighborhoods();

            $this->info(count($features) . ' GIS kaydı indirildi.');

            $records = $this->prepareRecords($features, $districts);

            $this->newLine();
            $this->info('Import özeti:');
            $this->table(
                ['Tür', 'Sayı'],
                [
                    ['Toplam GIS kaydı', count($features)],
                    ['Aktarılacak kayıt', count($records)],
                    ['Atlanan ÇEKİRDEKSİZ', count($features) - count($records)],
                    [
                        'Mahalle',
                        count(array_filter($records, fn ($r) =>
                            $r['area_type'] === 'neighborhood'
                        )),
                    ],
                    [
                        'Sanayi bölgesi',
                        count(array_filter($records, fn ($r) =>
                            $r['area_type'] === 'industrial_zone'
                        )),
                    ],
                ]
            );

            if ($this->option('dry-run')) {
                $this->info('Dry-run tamamlandı. Veritabanında değişiklik yapılmadı.');

                return self::SUCCESS;
            }

            if (! $this->confirm('GIS kayıtlarını PostgreSQL veritabanına aktarmak istiyor musunuz?')) {
                $this->warn('Import iptal edildi.');

                return self::SUCCESS;
            }

            $this->importRecords($records);

            $this->newLine();
            $this->info('Ankara GIS import başarıyla tamamlandı.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->newLine();
            $this->error('Import başarısız: ' . $exception->getMessage());

            return self::FAILURE;
        }
    }

    private function request(string $path, array $params): array
    {
        $response = Http::timeout(90)
            ->retry(3, 1000)
            ->get(self::BASE_URL . '/' . $path, $params);

        $response->throw();

        $data = $response->json();

        if (! is_array($data) || isset($data['error'])) {
            throw new RuntimeException(
                'ArcGIS API hatası: ' . json_encode($data, JSON_UNESCAPED_UNICODE)
            );
        }

        return $data;
    }

    private function fetchDistricts(): array
    {
        $data = $this->request('3/query', [
            'where' => '1=1',
            'outFields' => 'ID,AD',
            'returnGeometry' => 'false',
            'resultRecordCount' => 100,
            'f' => 'json',
        ]);

        $districts = [];

        foreach ($data['features'] ?? [] as $feature) {
            $attributes = $feature['attributes'] ?? [];

            $guid = $this->normalizeGuid($attributes['ID'] ?? '');
            $name = trim($attributes['AD'] ?? '');

            if ($guid === '' || $name === '') {
                throw new RuntimeException('Geçersiz ilçe kaydı bulundu.');
            }

            if (isset($districts[$guid])) {
                throw new RuntimeException("Tekrarlanan ilçe GUID: {$guid}");
            }

            $districts[$guid] = $name;
        }

        if (count($districts) !== 25) {
            throw new RuntimeException(
                '25 ilçe bekleniyordu. Bulunan: ' . count($districts)
            );
        }

        return $districts;
    }

    private function fetchNeighborhoods(): array
    {
        $countData = $this->request('2/query', [
            'where' => '1=1',
            'returnCountOnly' => 'true',
            'f' => 'json',
        ]);

        $expectedCount = (int) ($countData['count'] ?? 0);

        if ($expectedCount < 1) {
            throw new RuntimeException('GIS kayıt sayısı alınamadı.');
        }

        $this->info("Kaynak kayıt sayısı: {$expectedCount}");

        $features = [];

        for ($offset = 0; $offset < $expectedCount; $offset += self::PAGE_SIZE) {
            $data = $this->request('2/query', [
                'where' => '1=1',
                'outFields' => implode(',', [
                    'OBJECTID',
                    'ID',
                    'AD',
                    'KIMLIKNO',
                    'ILCEID',
                    'y98Oncesi',
                    'y98eGore',
                    'y2007Oncesi',
                    'y2007yeGore',
                    'y2018eGore',
                    'toplambina',
                ]),
                'returnGeometry' => 'true',
                'outSR' => 4326,
                'geometryPrecision' => 6,
                'resultOffset' => $offset,
                'resultRecordCount' => self::PAGE_SIZE,
                'f' => 'geojson',
            ]);

            $page = $data['features'] ?? [];

            if (count($page) === 0) {
                throw new RuntimeException(
                    "GIS sayfalaması beklenmedik şekilde sona erdi: {$offset}"
                );
            }

            array_push($features, ...$page);

            $this->line(
                'İndirildi: ' . count($features) . " / {$expectedCount}"
            );
        }

        if (count($features) !== $expectedCount) {
            throw new RuntimeException(
                'İndirilen kayıt sayısı kaynak sayısıyla eşleşmiyor.'
            );
        }

        return $features;
    }

    private function prepareRecords(array $features, array $districts): array
    {
        $records = [];
        $seenIds = [];

        foreach ($features as $feature) {
            $properties = $feature['properties'] ?? [];

            $name = trim($properties['AD'] ?? '');

            if ($name === '') {
                throw new RuntimeException('İsimsiz GIS kaydı bulundu.');
            }

            if (Str::contains(Str::upper($name), 'ÇEKİRDEKSİZ')) {
                continue;
            }

            $sourceId = $properties['KIMLIKNO'] ?? null;
            $objectId = $properties['OBJECTID'] ?? null;
            $sourceGuid = $this->normalizeGuid($properties['ID'] ?? '');
            $districtGuid = $this->normalizeGuid($properties['ILCEID'] ?? '');

            if (! is_numeric($sourceId) || ! is_numeric($objectId)) {
                throw new RuntimeException("Geçersiz kaynak kimliği: {$name}");
            }

            $sourceId = (int) $sourceId;
            $objectId = (int) $objectId;

            if (isset($seenIds[$sourceId])) {
                throw new RuntimeException(
                    "Tekrarlanan KIMLIKNO bulundu: {$sourceId}"
                );
            }

            $seenIds[$sourceId] = true;

            if ($sourceGuid === '' || $districtGuid === '') {
                throw new RuntimeException("GUID eksik: {$name}");
            }

            if (! isset($districts[$districtGuid])) {
                throw new RuntimeException(
                    "İlçe eşleştirilemedi: {$name} ({$districtGuid})"
                );
            }

            $geometry = $feature['geometry'] ?? null;

            if (
                ! is_array($geometry) ||
                ! in_array($geometry['type'] ?? '', ['Polygon', 'MultiPolygon'], true)
            ) {
                throw new RuntimeException("Geçersiz geometri: {$name}");
            }

            $district = $districts[$districtGuid];

            $areaType = Str::contains(Str::upper($name), 'OSB')
                ? 'industrial_zone'
                : 'neighborhood';

            $records[] = [
                'name' => $name,
                'slug' => Str::limit(
                    Str::slug("Ankara-{$district}-{$name}-{$sourceId}"),
                    180,
                    ''
                ),
                'district' => $district,
                'area_type' => $areaType,
                'source_id' => $sourceId,
                'source_object_id' => $objectId,
                'source_guid' => $sourceGuid,
                'source_district_guid' => $districtGuid,
                'geometry' => json_encode(
                    $geometry,
                    JSON_THROW_ON_ERROR
                ),
                'buildings_before_1998' => $properties['y98Oncesi'] ?? null,
                'buildings_under_1998_code' => $properties['y98eGore'] ?? null,
                'buildings_before_2007' => $properties['y2007Oncesi'] ?? null,
                'buildings_under_2007_code' => $properties['y2007yeGore'] ?? null,
                'buildings_under_2018_code' => $properties['y2018eGore'] ?? null,
                'total_buildings' => $properties['toplambina'] ?? null,
            ];
        }

        return $records;
    }

    private function importRecords(array $records): void
    {
        $now = now()->toDateTimeString();

        DB::transaction(function () use ($records, $now) {
            $bar = $this->output->createProgressBar(count($records));
            $bar->start();

            foreach ($records as $record) {
                $sql = <<<'SQL'
                    WITH incoming AS (
                        SELECT ST_Multi(
                            ST_CollectionExtract(
                                ST_MakeValid(
                                    ST_SetSRID(
                                        ST_GeomFromGeoJSON(?),
                                        4326
                                    )
                                ),
                                3
                            )
                        ) AS boundary
                    )
                    INSERT INTO neighborhoods (
                        name, slug, city, district, population,
                        is_active, area_type,
                        source, source_id, source_object_id,
                        source_guid, source_district_guid,
                        buildings_before_1998,
                        buildings_under_1998_code,
                        buildings_before_2007,
                        buildings_under_2007_code,
                        buildings_under_2018_code,
                        total_buildings,
                        center, boundary,
                        created_at, updated_at
                    )
                    SELECT
                        ?, ?, 'Ankara', ?, NULL,
                        TRUE, ?,
                        ?, ?, ?, ?, ?,
                        ?, ?, ?, ?, ?, ?,
                        ST_PointOnSurface(incoming.boundary)::geography,
                        incoming.boundary,
                        ?, ?
                    FROM incoming
                    WHERE NOT ST_IsEmpty(incoming.boundary)
                    ON CONFLICT (source, source_id)
                    DO UPDATE SET
                        name = EXCLUDED.name,
                        slug = EXCLUDED.slug,
                        city = EXCLUDED.city,
                        district = EXCLUDED.district,
                        is_active = TRUE,
                        area_type = EXCLUDED.area_type,
                        source_object_id = EXCLUDED.source_object_id,
                        source_guid = EXCLUDED.source_guid,
                        source_district_guid = EXCLUDED.source_district_guid,
                        buildings_before_1998 = EXCLUDED.buildings_before_1998,
                        buildings_under_1998_code = EXCLUDED.buildings_under_1998_code,
                        buildings_before_2007 = EXCLUDED.buildings_before_2007,
                        buildings_under_2007_code = EXCLUDED.buildings_under_2007_code,
                        buildings_under_2018_code = EXCLUDED.buildings_under_2018_code,
                        total_buildings = EXCLUDED.total_buildings,
                        center = EXCLUDED.center,
                        boundary = EXCLUDED.boundary,
                        updated_at = EXCLUDED.updated_at
                    SQL;

                $bindings = [
                    $record['geometry'],
                    $record['name'],
                    $record['slug'],
                    $record['district'],
                    $record['area_type'],
                    self::SOURCE,
                    $record['source_id'],
                    $record['source_object_id'],
                    $record['source_guid'],
                    $record['source_district_guid'],
                    $record['buildings_before_1998'],
                    $record['buildings_under_1998_code'],
                    $record['buildings_before_2007'],
                    $record['buildings_under_2007_code'],
                    $record['buildings_under_2018_code'],
                    $record['total_buildings'],
                    $now,
                    $now,
                ];

                $affected = DB::affectingStatement($sql, $bindings);

                if ($affected !== 1) {
                    throw new RuntimeException(
                        "Geometri kaydedilemedi: {$record['name']}"
                    );
                }

                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
        });
    }

    private function normalizeGuid(string $guid): string
    {
        return strtoupper(trim($guid, "{} \t\n\r\0\x0B"));
    }
}
