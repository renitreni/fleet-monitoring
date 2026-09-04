<?php

namespace Tests\Feature;

use App\Models\Car;
use App\Models\NotificationLog;
use App\Models\OilChange;
use App\Models\User;
use App\Notifications\OilChangeDueNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_sends_mail_for_overdue_oil_change(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $car = Car::factory()->for($user)->create([
            'current_mileage' => 50000,
        ]);

        $oilChange = OilChange::factory()->for($car)->create([
            'last_changed_at' => now()->subMonths(10),
            'last_changed_mileage' => 40000,
            'interval_months' => 6,
            'interval_mileage' => 5000,
        ]);
        $oilChange->computeNextDue();
        $oilChange->save();

        $this->artisan('oil-changes:check')->assertSuccessful();

        Notification::assertSentTo($user, OilChangeDueNotification::class, function ($notification) use ($car) {
            return $notification->car->id === $car->id
                && $notification->type === 'oil_change_overdue';
        });

        $this->assertDatabaseHas('notification_logs', [
            'notifiable_id' => $user->id,
            'notifiable_type' => User::class,
            'notification_type' => 'oil_change_overdue',
        ]);
    }

    public function test_command_sends_mail_for_due_soon_oil_change(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $car = Car::factory()->for($user)->create([
            'current_mileage' => 44500,
        ]);

        $oilChange = OilChange::factory()->for($car)->create([
            'last_changed_at' => now()->subMonths(5),
            'last_changed_mileage' => 40000,
            'interval_months' => 6,
            'interval_mileage' => 5000,
        ]);
        $oilChange->computeNextDue();
        $oilChange->save();

        $this->artisan('oil-changes:check')->assertSuccessful();

        Notification::assertSentTo($user, OilChangeDueNotification::class, function ($notification) use ($car) {
            return $notification->car->id === $car->id
                && $notification->type === 'oil_change_due_soon';
        });
    }

    public function test_command_does_not_send_duplicate_same_day_notifications(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $car = Car::factory()->for($user)->create([
            'current_mileage' => 50000,
        ]);

        $oilChange = OilChange::factory()->for($car)->create([
            'last_changed_at' => now()->subMonths(10),
            'last_changed_mileage' => 40000,
            'interval_months' => 6,
            'interval_mileage' => 5000,
        ]);
        $oilChange->computeNextDue();
        $oilChange->save();

        $this->artisan('oil-changes:check')->assertSuccessful();
        $this->artisan('oil-changes:check')->assertSuccessful();

        Notification::assertSentToTimes($user, OilChangeDueNotification::class, 1);
        $this->assertSame(1, NotificationLog::count());
    }

    public function test_command_does_not_notify_when_oil_change_is_not_due(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $car = Car::factory()->for($user)->create([
            'current_mileage' => 10000,
        ]);

        OilChange::factory()->for($car)->create([
            'last_changed_at' => now()->subMonth(),
            'last_changed_mileage' => 8000,
            'interval_months' => 6,
            'interval_mileage' => 5000,
            'next_due_date' => now()->addMonths(5),
            'next_due_mileage' => 13000,
        ]);

        $this->artisan('oil-changes:check')->assertSuccessful();

        Notification::assertNothingSent();
        $this->assertSame(0, NotificationLog::count());
    }

    public function test_command_notifies_each_owner_for_their_own_cars(): void
    {
        Notification::fake();

        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();

        $firstCar = Car::factory()->for($firstUser)->create([
            'current_mileage' => 50000,
        ]);
        $secondCar = Car::factory()->for($secondUser)->create([
            'current_mileage' => 50000,
        ]);

        $firstOilChange = OilChange::factory()->for($firstCar)->create([
            'last_changed_at' => now()->subMonths(10),
            'last_changed_mileage' => 40000,
            'interval_months' => 6,
            'interval_mileage' => 5000,
        ]);
        $firstOilChange->computeNextDue();
        $firstOilChange->save();

        $secondOilChange = OilChange::factory()->for($secondCar)->create([
            'last_changed_at' => now()->subMonths(10),
            'last_changed_mileage' => 40000,
            'interval_months' => 6,
            'interval_mileage' => 5000,
        ]);
        $secondOilChange->computeNextDue();
        $secondOilChange->save();

        $this->artisan('oil-changes:check')->assertSuccessful();

        Notification::assertSentTo($firstUser, OilChangeDueNotification::class);
        Notification::assertSentTo($secondUser, OilChangeDueNotification::class);
        $this->assertSame(2, NotificationLog::count());
    }
}
