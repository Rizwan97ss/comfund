<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use App\Support\BusinessCalendar;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', User::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => Str::lower(trim($this->input('email')))]);
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'regex:/^\+?[0-9]{10,15}$/', 'unique:users,phone'],
            'address' => ['nullable', 'string', 'max:500'],
            'joined_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.BusinessCalendar::today()->format('Y-m-d')],
            'monthly_contribution_paise' => ['nullable', 'integer', 'min:1', 'max:'.config('fund.max_amount_paise')],
        ];
    }
}
