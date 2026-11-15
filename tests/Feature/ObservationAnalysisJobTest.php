<?php

namespace Tests\Feature;

use App\Jobs\AnalyzeObservationJob;
use App\Models\Neighborhood;
use App\Models\Observation;
use App\Models\ObservationAnalysis;
use App\Models\ObservationMedia;
use App\Models\User;
use App\Notifications\ObservationAnalysisNotification;
use App\Services\GeminiAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ObservationAnalysisJobTest extends TestCase
{
    use RefreshDatabase;

    private function createAnalysis(): array
    {
        $user = User::factory()->create();

        $neighborhood = Neighborhood::create([
            'name' => 'Test Mahallesi',
            'slug' => 'test-mahallesi',
            'city' => 'Ankara',
            'district' => 'Çankaya',
            'is_active' => true,
        ]);

        $observation = Observation::create([
            'user_id' => $user->id,
            'neighborhood_id' => $neighborhood->id,
            'category' => 'waste',
            'description' => 'Mahallede çevre kirliliği gözlemlendi.',
            'status' => Observation::STATUS_PENDING,
        ]);

        $media = ObservationMedia::create([
            'observation_id' => $observation->id,
            'disk' => 'private',
            'path' => 'observations/test/test.jpg',
            'original_name' => 'test.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 1024,
        ]);

        $analysis = ObservationAnalysis::create([
            'observation_id' => $observation->id,
            'observation_media_id' => $media->id,
            'status' => ObservationAnalysis::STATUS_QUEUED,
            'provider' => 'gemini',
            'model' => 'gemini-3.5-flash-lite',
        ]);

        return [$user, $analysis];
    }

    public function test_successful_analysis_sends_notification_to_observation_owner(): void
    {
        Notification::fake();

        [$user, $analysis] = $this->createAnalysis();

        $gemini = $this->mock(GeminiAnalysisService::class);

        $gemini->shouldReceive('analyze')
            ->once()
            ->andReturn([
                'summary' => 'Fotoğrafta çevre atıkları tespit edildi.',
                'detected_category' => 'waste',
                'environmental_issue' => true,
                'confidence' => 0.95,
                'findings' => ['Plastik atıklar'],
                'recommendations' => ['Atıkların ayrıştırılması'],
            ]);

        (new AnalyzeObservationJob($analysis))->handle($gemini);

        $this->assertDatabaseHas('observation_analyses', [
            'id' => $analysis->id,
            'status' => ObservationAnalysis::STATUS_COMPLETED,
            'detected_category' => 'waste',
        ]);

        Notification::assertSentTo(
            $user,
            ObservationAnalysisNotification::class,
            function ($notification) use ($analysis) {
                return $notification->status === 'completed'
                    && $notification->analysisId === $analysis->id
                    && $notification->summary === 'Fotoğrafta çevre atıkları tespit edildi.';
            }
        );
    }

    public function test_failed_analysis_sends_failure_notification(): void
    {
        Notification::fake();

        [$user, $analysis] = $this->createAnalysis();

        (new AnalyzeObservationJob($analysis))->failed(
            new \RuntimeException('Gemini API error')
        );

        $this->assertDatabaseHas('observation_analyses', [
            'id' => $analysis->id,
            'status' => ObservationAnalysis::STATUS_FAILED,
            'error_code' => 'ANALYSIS_FAILED',
        ]);

        Notification::assertSentTo(
            $user,
            ObservationAnalysisNotification::class,
            function ($notification) use ($analysis) {
                return $notification->status === 'failed'
                    && $notification->analysisId === $analysis->id;
            }
        );
    }
}
