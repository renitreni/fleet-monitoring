<?php

namespace App\Notifications;

use App\Models\Car;
use App\Models\OilChange;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OilChangeDueNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Car $car,
        public OilChange $oilChange,
        public string $type = 'oil_change_due_soon'
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $isOverdue = $this->type === 'oil_change_overdue';
        $subject = $isOverdue
            ? "Oil change overdue for your {$this->car->year} {$this->car->make} {$this->car->model}"
            : "Oil change due soon for your {$this->car->year} {$this->car->make} {$this->car->model}";

        $line = $isOverdue
            ? 'Your oil change is overdue. Please schedule service as soon as possible.'
            : "Your oil change is coming up soon. Don't forget to schedule service.";

        return (new MailMessage)
            ->subject($subject)
            ->greeting("Hello {$notifiable->name},")
            ->line($line)
            ->line("Vehicle: {$this->car->year} {$this->car->make} {$this->car->model}")
            ->line("Next due date: {$this->oilChange->next_due_date->format('M j, Y')}")
            ->line("Next due mileage: {$this->oilChange->next_due_mileage} km")
            ->line("Current mileage: {$this->car->current_mileage} km")
            ->action('View Car', route('cars.show', $this->car))
            ->line('Thank you for using Motologiq!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->type,
            'car_id' => $this->car->id,
            'car_make' => $this->car->make,
            'car_model' => $this->car->model,
            'next_due_date' => $this->oilChange->next_due_date->format('M j, Y'),
            'next_due_mileage' => number_format($this->oilChange->next_due_mileage),
            'current_mileage' => number_format($this->car->current_mileage),
        ];
    }
}
