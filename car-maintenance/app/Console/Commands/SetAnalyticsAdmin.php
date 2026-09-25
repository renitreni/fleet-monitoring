<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('analytics:admin {email : Existing account email} {--revoke : Remove analytics administration access}')]
#[Description('Grant or revoke website analytics administration for an existing account')]
class SetAnalyticsAdmin extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error('No account exists with that email. Register first.');

            return self::FAILURE;
        }

        $user->is_analytics_admin = ! $this->option('revoke');
        $user->save();
        $this->info($user->is_analytics_admin ? 'Analytics administration granted.' : 'Analytics administration revoked.');

        return self::SUCCESS;
    }
}
