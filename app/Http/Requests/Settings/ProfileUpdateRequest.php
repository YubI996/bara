<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\RequiredIf;
use Illuminate\Validation\Rules\Unique;

class ProfileUpdateRequest extends FormRequest
{
    use ProfileValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, ValidationRule|Unique|RequiredIf|array<mixed>|string>>
     */
    public function rules(): array
    {
        return [
            ...$this->profileRules($this->user()?->id),
            // SEC-012: ganti email = jalur ambil alih akun lewat reset kata sandi; wajib bukti kepemilikan sesi.
            'current_password' => [
                Rule::requiredIf(fn (): bool => mb_strtolower($this->string('email')->toString()) !== mb_strtolower((string) $this->user()?->email)),
                'nullable',
                'string',
                'current_password',
            ],
        ];
    }
}
