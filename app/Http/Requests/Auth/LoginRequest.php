<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email'    => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'role'     => ['required', 'string', 'in:admin,accounting,registrar,student'],
        ];
    }

    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $credentials  = $this->only('email', 'password');
        $selectedRole = $this->input('role');

        if (! Auth::attempt($credentials, $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        $user     = Auth::user();
        $userRole = $this->getUserRole($user);

        if ($userRole !== $selectedRole) {
            Auth::logout();
            RateLimiter::hit($this->throttleKey());

            $roleLabel = $this->getRoleLabel($selectedRole);

            throw ValidationException::withMessages([
                'email' => "Invalid {$roleLabel} credentials. Please check your role selection or use the correct login portal.",
            ]);
        }

        if (! $user->is_active) {
            Auth::logout();
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => 'Your account has been deactivated. Please contact an administrator.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    protected function getUserRole($user): string
    {
        $role = $user->role;

        if (is_object($role)) {
            return $role->value ?? (string) $role;
        }

        return (string) $role;
    }

    protected function getRoleLabel(string $role): string
    {
        return match ($role) {
            'admin'      => 'Administrator',
            'accounting' => 'Accounting Staff',
            'registrar'  => 'Registrar Staff',
            'student'    => 'Student',
            default      => ucfirst($role),
        };
    }

    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    public function throttleKey(): string
    {
        return $this->string('email')
            ->lower()
            ->append('|' . $this->input('role'))
            ->append('|' . $this->ip())
            ->transliterate()
            ->value();
    }

    public function messages(): array
    {
        return [
            'role.required'     => 'Please select a role to continue.',
            'role.in'           => 'Invalid role selected.',
            'email.required'    => 'Email address is required.',
            'email.email'       => 'Please enter a valid email address.',
            'password.required' => 'Password is required.',
        ];
    }
}