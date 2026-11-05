<?php

namespace App\Http\Requests\Admin;

use App\Concerns\FinancialValidationRules;
use App\Concerns\RequiresIdempotencyKey;
use App\Enums\UserRole;
use App\Models\Fund;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreManualEntryRequest extends FormRequest
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
        $fund = Fund::query()->find($this->integer('fund_id'));

        return [
            ...$this->idempotencyRules(),
            'member_id' => ['required', 'integer', Rule::exists('users', 'id')->where('role', UserRole::Member->value)],
            'fund_id' => ['required', 'integer', Rule::exists('funds', 'id')->where('is_active', true)],
            'financial_account_id' => ['required', 'integer', Rule::exists('financial_accounts', 'id')->where('is_active', true)],
            'amount_paise' => $this->amountRules(),
            'applicable_month' => $fund?->isMonthly() ? ['required', 'date_format:Y-m'] : ['prohibited'],
            'payment_date' => $this->pastOrTodayRules(),
            'payment_method' => $this->paymentMethodRules(),
            'transaction_reference' => $this->referenceRules(),
            'attribution_note' => ['required', 'string', 'min:5', 'max:500'],
            'receipt' => ['nullable', ...$this->documentRules()],
        ];
    }
}
