<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Services\UserNameChanges;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    public function __construct(private UserNameChanges $nameChanges) {}

    /**
     * Validate and update the given user's profile information.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function update(User $user, array $input): void
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255', $this->nameChanges->uniqueRule($user)],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id),
            ],
            'country' => ['required', 'string', 'size:2'],
        ])->validateWithBag('updateProfileInformation');

        $sendVerification = DB::transaction(function () use ($user, $input): bool {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());
            $this->nameChanges->apply($lockedUser, $input['name']);

            if ($input['email'] !== $lockedUser->email && $lockedUser instanceof MustVerifyEmail) {
                $this->updateVerifiedUser($lockedUser, $input);

                return true;
            }

            $lockedUser->forceFill([
                'email' => $input['email'],
                'country' => $input['country'],
            ])->save();

            return false;
        });

        if ($sendVerification) {
            $user->fresh()->sendEmailVerificationNotification();
        }
    }

    /**
     * Update the given verified user's profile information.
     *
     * @param  array<string, string>  $input
     */
    protected function updateVerifiedUser(User $user, array $input): void
    {
        $user->forceFill([
            'email' => $input['email'],
            'country' => $input['country'],
            'email_verified_at' => null,
        ])->save();

        $user->sendEmailVerificationNotification();
    }
}
