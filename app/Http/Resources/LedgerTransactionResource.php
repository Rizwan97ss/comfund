<?php

namespace App\Http\Resources;

use App\Models\LedgerTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LedgerTransaction
 */
class LedgerTransactionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'type' => $this->type->value,
            'occurred_on' => $this->occurred_on->format('Y-m-d'),
            'narration' => $this->narration,
            'member' => $this->whenLoaded('member', fn (): ?string => $this->member?->name),
            'posted_by' => $this->whenLoaded('poster', fn (): ?string => $this->poster?->name),
            'reversed_by' => $this->whenLoaded('reversal', fn (): ?string => $this->reversal?->uuid),
            'reverses' => $this->whenLoaded('reverses', fn (): ?string => $this->reverses?->uuid),
            'entries' => LedgerEntryResource::collection($this->whenLoaded('entries')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
