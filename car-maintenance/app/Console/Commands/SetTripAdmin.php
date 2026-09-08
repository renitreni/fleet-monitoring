<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class SetTripAdmin extends Command
{
    protected $signature = 'trips:admin {email : Existing account email} {--revoke : Remove trip administration access}';
    protected $description = 'Grant or revoke trip administration for an existing account';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();
        if (! $user) {
            $this->error('No account exists with that email. Register first.');

            return self::FAILURE;
        }
        $user->is_trip_admin = ! $this->option('revoke');
        $user->save();
        $this->info($user->is_trip_admin ? 'Trip administration granted.' : 'Trip administration revoked.');

        return self::SUCCESS;
    }
}
