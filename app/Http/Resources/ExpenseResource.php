<?php

namespace App\Http\Resources;

use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Expense
 */
class ExpenseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'fund' => $this->fund->name,
            'account' => $this->account->name,
            'amount_paise' => $this->amount_paise,
            'beneficiary' => $this->beneficiary,
            'purpose' => $this->purpose,
            'expense_date' => $this->expense_date->format('Y-m-d'),
            'payment_method' => $this->payment_method->value,
            'transaction_reference' => $this->transaction_reference,
            'description' => $this->description,
            'created_by' => $this->creator->name,
            'documents' => DocumentResource::collection($this->documents),
            'ledger_uuid' => $this->ledgerTransaction->uuid,
            'is_reversed' => $this->ledgerTransaction->reversal !== null,
        ];
    }
}
