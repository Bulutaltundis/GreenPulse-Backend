<?php

namespace Tests\Feature;

use App\Models\Observation;
use App\Models\ObservationMedia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ObservationMediaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
    }

    private function createObservation(User $user): Observation
    {
        return Observation::factory()->create([
            'user_id' => $user->id,
        ]);
    }

    public function test_guest_cannot_upload_observation_media(): void
    {
        $user = User::factory()->create();
        $observation = $this->createObservation($user);

        $this->post(
            "/api/v1/observations/{$observation->id}/media",
            ['files' => [UploadedFile::fake()->image('photo.jpg')]]
        )->assertUnauthorized();
    }

    public function test_owner_can_upload_observation_media(): void
    {
        $user = User::factory()->create();
        $observation = $this->createObservation($user);

        $response = $this->actingAs($user, 'sanctum')
            ->post("/api/v1/observations/{$observation->id}/media", [
                'files' => [
                    UploadedFile::fake()->image('park.jpg', 800, 600),
                ],
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data');

        $mediaId = $response->json('data.0.id');

        $media = ObservationMedia::findOrFail($mediaId);

        $this->assertSame('private', $media->disk);

        Storage::disk('private')->assertExists($media->path);

        $this->assertDatabaseHas('observation_media', [
            'id' => $mediaId,
            'observation_id' => $observation->id,
            'disk' => 'private',
        ]);
    }

    public function test_upload_rejects_more_than_five_images(): void
    {
        $user = User::factory()->create();
        $observation = $this->createObservation($user);

        $files = [];

        for ($i = 0; $i < 6; $i++) {
            $files[] = UploadedFile::fake()->image("photo{$i}.jpg");
        }

        $this->actingAs($user, 'sanctum')
            ->post("/api/v1/observations/{$observation->id}/media", [
                'files' => $files,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['files']);
    }

    public function test_upload_rejects_invalid_file_types(): void
    {
        $user = User::factory()->create();
        $observation = $this->createObservation($user);

        $this->actingAs($user, 'sanctum')
            ->post("/api/v1/observations/{$observation->id}/media", [
                'files' => [
                    UploadedFile::fake()->create(
                        'document.pdf',
                        100,
                        'application/pdf'
                    ),
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['files.0']);
    }

    public function test_guest_cannot_view_observation_media(): void
    {
        $user = User::factory()->create();
        $observation = $this->createObservation($user);

        $response = $this->actingAs($user, 'sanctum')
            ->post("/api/v1/observations/{$observation->id}/media", [
                'files' => [
                    UploadedFile::fake()->image('photo.jpg'),
                ],
            ]);

        $mediaId = $response->json('data.0.id');

        auth()->guard('sanctum')->forgetUser();

        $this->get(
            "/api/v1/observations/{$observation->id}/media/{$mediaId}"
        )->assertUnauthorized();
    }

    public function test_owner_can_view_own_observation_media(): void
    {
        $user = User::factory()->create();
        $observation = $this->createObservation($user);

        $upload = $this->actingAs($user, 'sanctum')
            ->post("/api/v1/observations/{$observation->id}/media", [
                'files' => [
                    UploadedFile::fake()->image('photo.jpg'),
                ],
            ]);

        $mediaId = $upload->json('data.0.id');

        $this->get(
            "/api/v1/observations/{$observation->id}/media/{$mediaId}"
        )
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_user_cannot_view_another_users_media(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $observation = $this->createObservation($owner);

        $upload = $this->actingAs($owner, 'sanctum')
            ->post("/api/v1/observations/{$observation->id}/media", [
                'files' => [
                    UploadedFile::fake()->image('photo.jpg'),
                ],
            ]);

        $mediaId = $upload->json('data.0.id');

        $this->actingAs($otherUser, 'sanctum')
            ->get(
                "/api/v1/observations/{$observation->id}/media/{$mediaId}"
            )
            ->assertNotFound();
    }

    public function test_owner_can_delete_own_media(): void
    {
        $user = User::factory()->create();
        $observation = $this->createObservation($user);

        $upload = $this->actingAs($user, 'sanctum')
            ->post("/api/v1/observations/{$observation->id}/media", [
                'files' => [
                    UploadedFile::fake()->image('photo.jpg'),
                ],
            ]);

        $mediaId = $upload->json('data.0.id');
        $media = ObservationMedia::findOrFail($mediaId);

        $this->assertDatabaseHas('observation_media', [
            'id' => $mediaId,
        ]);

        $this->delete(
            "/api/v1/observations/{$observation->id}/media/{$mediaId}"
        )
            ->assertOk()
            ->assertJsonPath('success', true);

        Storage::disk('private')->assertMissing($media->path);

        $this->assertSoftDeleted('observation_media', [
            'id' => $mediaId,
        ]);
    }

    public function test_user_cannot_delete_another_users_media(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $observation = $this->createObservation($owner);

        $upload = $this->actingAs($owner, 'sanctum')
            ->post("/api/v1/observations/{$observation->id}/media", [
                'files' => [
                    UploadedFile::fake()->image('photo.jpg'),
                ],
            ]);

        $mediaId = $upload->json('data.0.id');

        $this->actingAs($otherUser, 'sanctum')
            ->delete(
                "/api/v1/observations/{$observation->id}/media/{$mediaId}"
            )
            ->assertNotFound();

        $this->assertDatabaseHas('observation_media', [
            'id' => $mediaId,
            'deleted_at' => null,
        ]);
    }
}
