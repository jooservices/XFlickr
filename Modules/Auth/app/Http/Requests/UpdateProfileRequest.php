<?php

declare(strict_types=1);

namespace Modules\Auth\Http\Requests;

use App\Http\Requests\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

final class UpdateProfileRequest extends Request
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('password') === '') {
            $this->merge(['password' => null]);
        }

        if ($this->input('current_password') === '') {
            $this->merge(['current_password' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->user();

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', Password::defaults()],
            'current_password' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $user = $this->user();
            if (! $user instanceof User) {
                return;
            }

            $changingEmail = $this->string('email')->toString() !== $user->email;
            $changingPassword = $this->filled('password');

            if (! $changingEmail && ! $changingPassword) {
                return;
            }

            if (! $this->filled('current_password')) {
                $validator->errors()->add(
                    'current_password',
                    __('Current password is required to change email or password.'),
                );

                return;
            }

            if (! Hash::check($this->string('current_password')->toString(), $user->password)) {
                $validator->errors()->add('current_password', __('Current password is incorrect.'));
            }
        });
    }

    public function userName(): string
    {
        return (string) $this->validated('name');
    }

    public function userEmail(): string
    {
        return (string) $this->validated('email');
    }

    public function hasPassword(): bool
    {
        return $this->filled('password');
    }

    public function userPassword(): string
    {
        return (string) $this->validated('password');
    }
}
