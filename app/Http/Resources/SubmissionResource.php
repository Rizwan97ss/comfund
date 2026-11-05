<?php

namespace App\Http\Resources;

use App\Models\PaymentSubmission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PaymentSubmission
 */
class SubmissionResource extends JsonResource
{
    /**
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isAdmin = $request->user()?->isAdmin() === true;

        return [
            'uuid' => $this->uuid,
            'fund' => ['id' => $this->fund->id, 'name' => $this->fund->name, 'code' => $this->fund->code],
            'amount_paise' => $this->amount_paise,
            'applicable_month' => $this->applicable_month?->format('Y-m'),
            'payment_date' => $this->payment_date->format('Y-m-d'),
            'payment_method' => $this->payment_method->value,
            'transaction_reference' => $this->transaction_reference,
            'member_note' => $this->member_note,
            'status' => $this->status->value,
            'source' => $this->source->value,
            'rejection_reason' => $this->rejection_reason,
            'reviewer' => $this->reviewer?->name,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'submitted_at' => $this->created_at?->toIso8601String(),
            'is_reversed' => $this->ledgerTransaction?->reversal !== null,
            'document' => $this->document === null ? null : new DocumentResource($this->document),
            $this->mergeWhen($isAdmin, fn (): array => [
                'member' => ['id' => $this->member->id, 'name' => $this->member->name, 'member_code' => $this->member->member_code],
                'submitted_by' => $this->submitter->name,
                'attribution_note' => $this->attribution_note,
                'account' => $this->account?->name,
                'ledger_uuid' => $this->ledgerTransaction?->uuid,
                'same_receipt_count' => $this->resource->getAttribute('same_receipt_count'),
            ]),
        ];
    }
}
