<?php

namespace App\Http\Requests\Admin;

use App\Concerns\FinancialValidationRules;
use App\Concerns\RequiresIdempotencyKey;
use App\Models\Expense;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseRequest extends FormRequest
{
    use FinancialValidationRules, RequiresIdempotencyKey;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Expense::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            ...$this->idempotencyRules(),
            'fund_id' => ['required', 'integer', Rule::exists('funds', 'id')->where('is_active', true)],
            'financial_account_id' => ['required', 'integer', Rule::exists('financial_accounts', 'id')->where('is_active', true)],
            'amount_paise' => $this->amountRules(),
            'beneficiary' => ['required', 'string', 'max:160'],
            'purpose' => ['required', 'string', 'max:160'],
            'expense_date' => $this->pastOrTodayRules(),
            'payment_method' => $this->paymentMethodRules(),
            'transaction_reference' => $this->referenceRules(),
            'description' => ['nullable', 'string', 'max:2000'],
            'documents' => ['nullable', 'array', 'max:5'],
            'documents.*' => $this->documentRules(),
        ];
    }
}
