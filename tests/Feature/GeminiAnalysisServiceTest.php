<?php

namespace Tests\Feature;

use App\Models\Observation;
use App\Models\ObservationAnalysis;
use App\Models\ObservationMedia;
use App\Services\GeminiAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use JsonException;
use RuntimeException;
use Tests\TestCase;

class GeminiAnalysisServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');

        config([
            'services.gemini.api_key' => 'test-api-key',
            'services.gemini.model' => 'gemini-3.5-flash-lite',
            'services.gemini.timeout' => 90,
        ]);
    }

    private function createAnalysis(bool $withImage = true): ObservationAnalysis
    {
        $observation = Observation::factory()->create();

        $media = ObservationMedia::create([
            'observation_id' => $observation->id,
            'disk' => 'private',
            'path' => "observations/{$observation->id}/test.jpg",
            'original_name' => 'test.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 1024,
        ]);

        if ($withImage) {
            $image = imagecreatetruecolor(1, 1);

            ob_start();
            imagejpeg($image);
            $imageContents = ob_get_clean();

            imagedestroy($image);

            Storage::disk('private')->put($media->path, $imageContents);
        }

        return ObservationAnalysis::create([
            'observation_id' => $observation->id,
            'observation_media_id' => $media->id,
            'status' => ObservationAnalysis::STATUS_QUEUED,
            'provider' => 'gemini',
            'model' => 'gemini-3.5-flash-lite',
        ]);
    }

    private function validGeminiResponse(array $overrides = []): array
    {
        $result = array_merge([
            'summary' => 'Park alanında çevresel atıklar görülüyor.',
            'detected_category' => 'waste',
            'environmental_issue' => true,
            'confidence' => 0.92,
            'findings' => [
                'Yerde plastik atıklar görülüyor.',
            ],
            'recommendations' => [
                'Atıklar uygun geri dönüşüm kutularına ayrılmalı.',
            ],
        ], $overrides);

        return [
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            [
                                'text' => json_encode(
                                    $result,
                                    JSON_UNESCAPED_UNICODE
                                ),
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function test_gemini_successfully_analyzes_image(): void
    {
        Http::fake([
            '*' => Http::response($this->validGeminiResponse(), 200),
        ]);

        $analysis = $this->createAnalysis();

        $result = app(GeminiAnalysisService::class)->analyze($analysis);

        $this->assertSame('waste', $result['detected_category']);
        $this->assertSame(0.92, $result['confidence']);

        Http::assertSent(function ($request) {
            return str_contains(
                $request->url(),
                'gemini-3.5-flash-lite:generateContent'
            )
                && $request->hasHeader('x-goog-api-key', 'test-api-key')
                && isset($request['contents'][0]['parts'][1]['inline_data']);
        });
    }

    public function test_missing_image_throws_exception(): void
    {
        $analysis = $this->createAnalysis(false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Image file not found.');

        app(GeminiAnalysisService::class)->analyze($analysis);
    }

    public function test_invalid_category_is_rejected(): void
    {
        Http::fake([
            '*' => Http::response(
                $this->validGeminiResponse([
                    'detected_category' => 'invalid_category',
                ]),
                200
            ),
        ]);

        $analysis = $this->createAnalysis();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Gemini returned an invalid analysis format.'
        );

        app(GeminiAnalysisService::class)->analyze($analysis);
    }

    public function test_invalid_json_is_rejected(): void
    {
        Http::fake([
            '*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => '{invalid json'],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $analysis = $this->createAnalysis();

        $this->expectException(JsonException::class);

        app(GeminiAnalysisService::class)->analyze($analysis);
    }
}
