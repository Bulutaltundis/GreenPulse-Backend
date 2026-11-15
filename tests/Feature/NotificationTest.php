<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ObservationReviewedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private function createNotification(User $user, string $status = 'approved'): void
    {
        $user->notify(new ObservationReviewedNotification(
            observationId: 1,
            neighborhoodId: 10,
            neighborhoodName: 'Çankaya',
            status: $status,
            reason: $status === 'rejected' ? 'Yeterli kanıt bulunamadı.' : null,
        ));
    }

    public function test_guest_cannot_access_notifications(): void
    {
        $this->getJson('/api/v1/notifications')
            ->assertUnauthorized();

        $this->getJson('/api/v1/notifications/unread-count')
            ->assertUnauthorized();
    }

    public function test_user_can_list_own_notifications(): void
    {
        $user = User::factory()->create();

        $this->createNotification($user);
        $this->createNotification($user, 'rejected');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.unread_count', 2);
    }

    public function test_user_can_get_unread_notification_count(): void
    {
        $user = User::factory()->create();

        $this->createNotification($user);
        $this->createNotification($user);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 2);
    }

    public function test_user_can_mark_notification_as_read(): void
    {
        $user = User::factory()->create();

        $this->createNotification($user);

        $notification = $user->notifications()->firstOrFail();

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/notifications/{$notification->id}/read")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
        ]);

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_user_cannot_read_another_users_notification(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $this->createNotification($owner);

        $notification = $owner->notifications()->firstOrFail();

        $this->actingAs($otherUser, 'sanctum')
            ->patchJson("/api/v1/notifications/{$notification->id}/read")
            ->assertNotFound();

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_user_can_mark_all_notifications_as_read(): void
    {
        $user = User::factory()->create();

        $this->createNotification($user);
        $this->createNotification($user);
        $this->createNotification($user, 'rejected');

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/v1/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('data.updated_count', 3)
            ->assertJsonPath('data.unread_count', 0);

        $this->assertSame(0, $user->unreadNotifications()->count());
    }

    public function test_user_only_sees_own_notifications(): void
    {
        $user = User::factory()->create();
        $anotherUser = User::factory()->create();

        $this->createNotification($user);
        $this->createNotification($anotherUser);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 1);
    }
}
