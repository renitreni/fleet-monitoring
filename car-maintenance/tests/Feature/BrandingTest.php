<?php

namespace Tests\Feature;

use App\Models\Car;
use App\Models\OilChange;
use App\Models\User;
use App\Notifications\OilChangeDueNotification;
use Tests\TestCase;

class BrandingTest extends TestCase
{
    public function test_application_shell_renders_motologiq_identity_and_icons(): void
    {
        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertSee('<title inertia>Motologiq</title>', false)
            ->assertSee('<meta name="theme-color" content="#f3f1ec">', false)
            ->assertSee("localStorage.getItem('motologiq-theme')", false)
            ->assertSee('<link rel="icon" href="/favicon.svg" type="image/svg+xml">', false)
            ->assertSee('<link rel="apple-touch-icon" href="/apple-touch-icon.png">', false);
    }

    public function test_oil_change_email_uses_motologiq_sign_off(): void
    {
        $user = new User(['name' => 'Renier']);
        $car = new Car([
            'make' => 'Honda',
            'model' => 'Civic',
            'year' => 2020,
            'current_mileage' => 45000,
        ]);
        $car->id = 1;
        $oilChange = new OilChange([
            'next_due_date' => '2026-10-01',
            'next_due_mileage' => 50000,
        ]);
        $notification = new OilChangeDueNotification($car, $oilChange);

        $message = $notification->toMail($user);

        $this->assertContains('Thank you for using Motologiq!', $message->outroLines);
    }
}
