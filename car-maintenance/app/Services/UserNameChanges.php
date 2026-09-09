<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UserNameChanges
{
    public const LIMIT = 3;

    public function displayName(string $name): string
    {
        return Str::squish($name);
    }

    public function normalizedName(string $name): string
    {
        return Str::lower($this->displayName($name));
    }

    public function uniqueRule(?User $except = null): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($except): void {
            if (! is_string($value)) {
                return;
            }

            $query = User::query()->where('normalized_name', $this->normalizedName($value));

            if ($except) {
                $query->whereKeyNot($except->getKey());
            }

            if ($query->exists()) {
                $fail('That display name is already taken.');
            }
        };
    }

    public function availableName(string $preferredName): string
    {
        $baseName = $this->displayName($preferredName);
        $candidate = $baseName;
        $suffix = 2;

        while (User::query()->where('normalized_name', $this->normalizedName($candidate))->exists()) {
            $ending = " {$suffix}";
            $candidate = Str::limit($baseName, 255 - Str::length($ending), '').$ending;
            $suffix++;
        }

        return $candidate;
    }

    /** @return array{limit: int, used: int, remaining: int, next_available_at: ?string} */
    public function quota(User $user): array
    {
        $history = $this->recentHistory($user);
        $remaining = max(0, self::LIMIT - count($history));

        return [
            'limit' => self::LIMIT,
            'used' => count($history),
            'remaining' => $remaining,
            'next_available_at' => $remaining === 0
                ? CarbonImmutable::parse($history[0])->addYear()->toIso8601String()
                : null,
        ];
    }

    /**
     * Apply a validated name to a user locked inside a database transaction.
     *
     * @throws ValidationException
     */
    public function apply(User $user, string $name): void
    {
        $displayName = $this->displayName($name);

        if ($displayName === $user->name) {
            return;
        }

        $history = $this->recentHistory($user);

        if (count($history) >= self::LIMIT) {
            $nextDate = CarbonImmutable::parse($history[0])->addYear()->toFormattedDateString();

            throw ValidationException::withMessages([
                'name' => "You have used all three name changes for the last 12 months. You can change it again on {$nextDate}.",
            ])->errorBag('updateProfileInformation');
        }

        $user->name = $displayName;
        $user->normalized_name = $this->normalizedName($displayName);
        $user->name_change_history = [...$history, now()->toIso8601String()];
    }

    /** @return list<string> */
    private function recentHistory(User $user): array
    {
        $cutoff = now()->subYear();

        return collect($user->name_change_history ?? [])
            ->filter(fn (string $changedAt): bool => CarbonImmutable::parse($changedAt)->isAfter($cutoff))
            ->sort()
            ->values()
            ->all();
    }
}
