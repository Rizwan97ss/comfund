<?php

namespace App\Http\Resources;

use App\Models\DuesObligation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DuesObligation
 */
class DuesObligationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'period' => $this->period->format('Y-m'),
            'amount_paise' => $this->amount_paise,
            'paid_paise' => $this->paidPaise(),
            'waived_paise' => $this->waivedPaise(),
            'outstanding_paise' => $this->outstandingPaise(),
            'status' => $this->settlementStatus(),
        ];
    }
}
