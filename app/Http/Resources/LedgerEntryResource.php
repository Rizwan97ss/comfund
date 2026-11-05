<?php

namespace App\Http\Resources;

use App\Models\LedgerEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LedgerEntry
 */
class LedgerEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'occurred_on' => $this->occurred_on->format('Y-m-d'),
            'fund' => $this->whenLoaded('fund', fn (): string => $this->fund->name),
            'account' => $this->whenLoaded('account', fn (): string => $this->account->name),
            'kind' => $this->kind->value,
            'amount_paise' => $this->amount_paise,
            'narration' => $this->whenLoaded('transaction', fn (): string => $this->transaction->narration),
            'transaction_type' => $this->whenLoaded('transaction', fn (): string => $this->transaction->type->value),
        ];
    }
}
