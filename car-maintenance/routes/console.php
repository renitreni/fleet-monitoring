<?php

use App\Console\Commands\GenerateAiBlogPosts;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Stringable;

Schedule::command('oil-changes:check')->dailyAt('08:00');

Schedule::command('trips:prune-locations')->everyMinute()->withoutOverlapping();

Schedule::command('blog:publish-scheduled')->everyMinute()->withoutOverlapping()->onOneServer();

$notifyBlogGenerationResult = function (Event $event, Stringable $output): void {
    $recipient = config('blog.ai.notification_email');

    if (! $recipient) {
        return;
    }

    $status = $event->exitCode === 0 ? 'succeeded' : 'failed';

    Mail::raw("AI blog generation {$status}.\n\n{$output}", function ($message) use ($recipient, $status): void {
        $message->to($recipient)->subject("AI blog generation {$status}");
    });
};

Schedule::command('blog:generate-ai')
    ->dailyAt(config('blog.ai.generation_time'))
    ->timezone(config('blog.timezone'))
    ->withoutOverlapping(180)
    ->onOneServer()
    ->thenWithOutput($notifyBlogGenerationResult);

// Retry hourly for the next 2 hours, only while the daily quota is incomplete.
foreach ([1, 2] as $hoursLater) {
    Schedule::command('blog:generate-ai')
        ->dailyAt(Carbon::createFromFormat('H:i', config('blog.ai.generation_time'))->addHours($hoursLater)->format('H:i'))
        ->timezone(config('blog.timezone'))
        ->when(fn (): bool => app(GenerateAiBlogPosts::class)->remainingQuota() > 0)
        ->withoutOverlapping(180)
        ->onOneServer()
        ->thenWithOutput($notifyBlogGenerationResult);
}

Schedule::command('blog:prune-views')->dailyAt('03:10')->timezone(config('blog.timezone'))->withoutOverlapping();

Schedule::command('analytics:prune-page-views')->dailyAt('03:00')->withoutOverlapping();

Schedule::command('analytics:update-country-database')->dailyAt('03:30')->withoutOverlapping();
