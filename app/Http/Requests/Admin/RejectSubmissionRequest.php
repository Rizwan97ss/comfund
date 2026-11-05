<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class RejectSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('review', $this->route('submission')) ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:5', 'max:500']];
    }
}
