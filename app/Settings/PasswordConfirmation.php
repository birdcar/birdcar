<?php

namespace App\Settings;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\ConfirmPassword;

/**
 * Recent password confirmation for sensitive account settings. It shares Laravel's session timestamp
 * with the password.confirm middleware, so a confirmation in either place counts for both.
 */
final class PasswordConfirmation
{
    private const int MaxAttempts = 5;

    public function recentlyConfirmed(): bool
    {
        $confirmedAt = session('auth.password_confirmed_at', 0);

        return is_numeric($confirmedAt) && (time() - (int) $confirmedAt) < (int) config('auth.password_timeout', 10800);
    }

    /**
     * @throws ValidationException when the password is wrong or attempts are exhausted
     */
    public function confirm(Authenticatable $user, string $password, string $field): void
    {
        $throttleKey = 'settings-confirm-password:'.$user->getAuthIdentifier();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MaxAttempts)) {
            throw ValidationException::withMessages([
                $field => 'Too many attempts. Try again in '.RateLimiter::availableIn($throttleKey).' seconds.',
            ]);
        }

        if (! app(ConfirmPassword::class)(Auth::guard('web'), $user, $password)) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages([$field => "That password isn't right."]);
        }

        RateLimiter::clear($throttleKey);
        session()->passwordConfirmed();
    }
}
