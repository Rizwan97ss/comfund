<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage', $this->route('member')) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => Str::lower(trim($this->input('email')))]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $member = $this->route('member');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'email' => ['sometimes', 'required', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($member)],
            'phone' => ['sometimes', 'required', 'string', 'regex:/^\+?[0-9]{10,15}$/', Rule::unique('users', 'phone')->ignore($member)],
            'address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'monthly_contribution_paise' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:'.config('fund.max_amount_paise')],
        ];
    }
}
