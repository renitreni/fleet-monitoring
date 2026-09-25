<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('blog:admin {email : Existing account email} {--revoke : Remove blog administration access}')]
#[Description('Grant or revoke blog administration for an existing account')]
class SetBlogAdmin extends Command
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

        $user->is_blog_admin = ! $this->option('revoke');
        $user->save();
        $this->info($user->is_blog_admin ? 'Blog administration granted.' : 'Blog administration revoked.');

        return self::SUCCESS;
    }
}
