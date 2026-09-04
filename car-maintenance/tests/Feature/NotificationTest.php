<?php

namespace Tests\Feature;

use App\Models\Car;
use App\Models\NotificationLog;
use App\Models\OilChange;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_is_created_when_car_is_added()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/cars', [
            'make' => 'Toyota',
            'model' => 'Camry',
            'year' => 2020,
            'current_mileage' => 50000,
            'country' => 'US',
            'vin' => '12345678901234567',
        ]);

        $this->assertDatabaseHas('notification_logs', [
            'notifiable_id' => $user->id,
            'notifiable_type' => User::class,
            'notification_type' => 'car_added',
        ]);

        $notification = NotificationLog::first();
        $this->assertEquals('Toyota', $notification->data['car_make']);
        $this->assertEquals('Camry', $notification->data['car_model']);
    }

    public function test_notification_is_created_when_oil_change_is_recorded()
    {
        $user = User::factory()->create();
        $car = Car::factory()->for($user)->create();

        $response = $this->actingAs($user)->post("/cars/{$car->id}/oil-changes", [
            'last_changed_at' => '2024-01-01',
            'last_changed_mileage' => 40000,
            'interval_months' => 6,
            'interval_mileage' => 5000,
        ]);

        $this->assertDatabaseHas('notification_logs', [
            'notifiable_id' => $user->id,
            'notifiable_type' => User::class,
            'notification_type' => 'oil_change_recorded',
        ]);

        $notification = NotificationLog::where('notification_type', 'oil_change_recorded')->first();
        $this->assertNotEmpty($notification->data['car_make']);
        $this->assertNotEmpty($notification->data['car_model']);
        $this->assertEquals($car->make, $notification->data['car_make']);
        $this->assertEquals($car->model, $notification->data['car_model']);
    }

    public function test_oil_change_overdue_notification_is_created_by_command()
    {
        $user = User::factory()->create();
        $car = Car::factory()->for($user)->create([
            'current_mileage' => 50000,
        ]);

        // Create an oil change that is overdue (last changed 10 months ago with 6 month interval)
        $oilChange = OilChange::factory()->for($car)->create([
            'last_changed_at' => now()->subMonths(10),
            'last_changed_mileage' => 40000,
            'interval_months' => 6,
            'interval_mileage' => 5000,
        ]);
        $oilChange->computeNextDue();
        $oilChange->save();

        $this->artisan('oil-changes:check');

        $this->assertDatabaseHas('notification_logs', [
            'notifiable_id' => $user->id,
            'notifiable_type' => User::class,
            'notification_type' => 'oil_change_overdue',
        ]);
    }

    public function test_oil_change_due_soon_notification_is_created_by_command()
    {
        $user = User::factory()->create();
        $car = Car::factory()->for($user)->create([
            'current_mileage' => 44500, // 500 km before due
        ]);

        // Create an oil change that is due soon (next due at 45000 km)
        $oilChange = OilChange::factory()->for($car)->create([
            'last_changed_at' => now()->subMonths(5), // 1 month before 6-month interval
            'last_changed_mileage' => 40000,
            'interval_months' => 6,
            'interval_mileage' => 5000,
        ]);
        $oilChange->computeNextDue();
        $oilChange->save();

        $this->artisan('oil-changes:check');

        $this->assertDatabaseHas('notification_logs', [
            'notifiable_id' => $user->id,
            'notifiable_type' => User::class,
            'notification_type' => 'oil_change_due_soon',
        ]);
    }

    public function test_user_can_mark_notification_as_read()
    {
        $user = User::factory()->create();
        $notification = NotificationLog::factory()->for($user, 'notifiable')->create([
            'notification_type' => 'test_notification',
            'data' => ['message' => 'Test message'],
            'read_at' => null,
        ]);

        $response = $this->actingAs($user)->post("/notifications/{$notification->id}/read");

        $response->assertOk();
        $response->assertJson(['success' => true, 'unread_count' => 0]);
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_user_cannot_mark_another_users_notification_as_read()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $notification = NotificationLog::factory()->for($otherUser, 'notifiable')->create([
            'notification_type' => 'test_notification',
            'data' => ['message' => 'Test message'],
            'read_at' => null,
        ]);

        $this->actingAs($user)->post("/notifications/{$notification->id}/read")
            ->assertStatus(404);

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_notification_history_can_be_retrieved()
    {
        $user = User::factory()->create();
        NotificationLog::factory()->for($user, 'notifiable')->create([
            'notification_type' => 'test_notification',
            'data' => ['message' => 'Test message'],
        ]);

        $response = $this->actingAs($user)->get('/notifications');

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('Notifications/Index')
                ->has('notifications', 1)
        );
    }

    public function test_recent_notifications_api_endpoint_works()
    {
        $user = User::factory()->create();
        NotificationLog::factory()->for($user, 'notifiable')->create([
            'notification_type' => 'test_notification',
            'data' => ['message' => 'Test message'],
        ]);

        $response = $this->actingAs($user)->get('/api/notifications/recent');

        $response->assertOk();
        $response->assertJsonStructure([
            'notifications',
            'unread_count',
        ]);
        $this->assertCount(1, $response->json('notifications'));
    }
}
