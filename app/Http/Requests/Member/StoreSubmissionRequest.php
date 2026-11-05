<?php

namespace App\Http\Requests\Member;

use App\Concerns\FinancialValidationRules;
use App\Concerns\RequiresIdempotencyKey;
use App\Models\Fund;
use App\Models\PaymentSubmission;
use App\Support\BusinessCalendar;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSubmissionRequest extends FormRequest
{
    use FinancialValidationRules, RequiresIdempotencyKey;

    public function authorize(): bool
    {
        return $this->user()?->can('create', PaymentSubmission::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $fund = Fund::query()->find($this->integer('fund_id'));

        return [
            ...$this->idempotencyRules(),
            'fund_id' => ['required', 'integer', Rule::exists('funds', 'id')->where('is_active', true)],
            'amount_paise' => $this->amountRules(),
            'applicable_month' => $fund?->isMonthly() ? ['required', 'date_format:Y-m'] : ['prohibited'],
            'payment_date' => $this->pastOrTodayRules(),
            'payment_method' => $this->paymentMethodRules(),
            'transaction_reference' => $this->referenceRules(),
            'member_note' => ['nullable', 'string', 'max:500'],
            'receipt' => ['required_unless:payment_method,cash', 'nullable', ...$this->documentRules()],
            'resubmission_of' => ['nullable', 'uuid'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $month = $this->input('applicable_month');

            if (! is_string($month) || preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) !== 1) {
                return;
            }

            $joined = $this->user()?->joined_on;

            if ($joined !== null && $month < $joined->format('Y-m')) {
                $validator->errors()->add('applicable_month', 'The month cannot be before you joined.');
            }

            if (BusinessCalendar::month($month)->greaterThan(BusinessCalendar::currentMonth()->addMonths(24))) {
                $validator->errors()->add('applicable_month', 'Advance payments can cover at most 24 months ahead.');
            }
        }];
    }
}
