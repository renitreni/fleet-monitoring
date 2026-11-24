<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BlogGenerationScheduleTest extends TestCase
{
    public static function generationResults(): array
    {
        return [
            'success' => [0, 'succeeded'],
            'failure' => [1, 'failed'],
        ];
    }

    #[DataProvider('generationResults')]
    public function test_scheduled_generation_emails_status_and_output(int $exitCode, string $status): void
    {
        config(['blog.ai.notification_email' => 'recipient@example.com']);
        Event::fake([MessageSent::class]);

        $this->runScheduledGeneration($exitCode);

        Event::assertDispatchedTimes(MessageSent::class, 1);
        Event::assertDispatched(MessageSent::class, function (MessageSent $event) use ($status): bool {
            return $event->message->getTo()[0]->getAddress() === 'recipient@example.com'
                && $event->message->getSubject() === "AI blog generation {$status}"
                && str_contains($event->message->getTextBody(), 'Generation result');
        });
    }

    public function test_scheduled_generation_sends_no_email_without_a_recipient(): void
    {
        config(['blog.ai.notification_email' => null]);
        Event::fake([MessageSent::class]);

        $this->runScheduledGeneration(0);

        Event::assertNotDispatched(MessageSent::class);
    }

    private function runScheduledGeneration(int $exitCode): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event): bool => str_contains($event->command ?? '', 'blog:generate-ai'));

        $this->assertNotNull($event);

        $outputPath = tempnam(sys_get_temp_dir(), 'blog-schedule-');
        $event->sendOutputTo($outputPath);
        $event->command = escapeshellarg(PHP_BINARY).' -r '.escapeshellarg("fwrite(STDOUT, 'Generation result'); exit({$exitCode});");

        try {
            $event->run($this->app);
        } finally {
            unlink($outputPath);
        }
    }
}
