<?php

namespace App\Http\Requests\Admin;

use App\Concerns\FinancialValidationRules;
use App\Concerns\RequiresIdempotencyKey;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransferRequest extends FormRequest
{
    use FinancialValidationRules, RequiresIdempotencyKey;

    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            ...$this->idempotencyRules(),
            'fund_id' => ['required', 'integer', 'exists:funds,id'],
            'from_account_id' => ['required', 'integer', Rule::exists('financial_accounts', 'id')->where('is_active', true)],
            'to_account_id' => ['required', 'integer', 'different:from_account_id', Rule::exists('financial_accounts', 'id')->where('is_active', true)],
            'amount_paise' => $this->amountRules(),
            'transfer_date' => $this->pastOrTodayRules(),
            'transaction_reference' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9\-\/_. ]+$/'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
