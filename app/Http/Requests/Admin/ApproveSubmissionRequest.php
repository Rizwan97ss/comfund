<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApproveSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('review', $this->route('submission')) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'financial_account_id' => ['required', 'integer', Rule::exists('financial_accounts', 'id')->where('is_active', true)],
        ];
    }
}
