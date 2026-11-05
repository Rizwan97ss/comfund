<?php

namespace App\Http\Resources;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AuditLog
 */
class AuditLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'sequence' => $this->sequence,
            'occurred_at' => $this->occurred_at,
            'category' => $this->category,
            'action' => $this->action,
            'severity' => $this->severity,
            'actor' => $this->actor_label,
            'actor_role' => $this->actor_role,
            'subject_type' => $this->subject_type,
            'subject_id' => $this->subject_id,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'request_id' => $this->request_id,
            'http_method' => $this->http_method,
            'route' => $this->route,
            'status_code' => $this->status_code,
            'payload' => $this->payload === null ? null : json_decode($this->payload, true),
            'hash' => $this->hash,
        ];
    }
}
