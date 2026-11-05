<?php

namespace App\Concerns;

use App\Enums\PaymentMethod;
use App\Support\BusinessCalendar;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

trait FinancialValidationRules
{
    /**
     * @return list<string>
     */
    protected function amountRules(): array
    {
        return ['required', 'integer', 'min:1', 'max:'.config('fund.max_amount_paise')];
    }

    /**
     * @return list<string>
     */
    protected function pastOrTodayRules(): array
    {
        return ['required', 'date_format:Y-m-d', 'before_or_equal:'.BusinessCalendar::today()->format('Y-m-d')];
    }

    /**
     * @return list<mixed>
     */
    protected function paymentMethodRules(): array
    {
        return ['required', Rule::enum(PaymentMethod::class)];
    }

    /**
     * References are required for every method that produces one, because they are
     * what makes duplicate payments detectable.
     *
     * @return list<string>
     */
    protected function referenceRules(): array
    {
        return ['nullable', 'required_unless:payment_method,cash,other', 'string', 'max:100', 'regex:/^[A-Za-z0-9\-\/_. ]+$/'];
    }

    /**
     * @return list<mixed>
     */
    protected function documentRules(): array
    {
        return [File::types(config('fund.receipts.mimes'))->max((int) config('fund.receipts.max_kilobytes'))];
    }
}
