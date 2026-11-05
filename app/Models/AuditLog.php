<?php

namespace App\Models;

use App\Concerns\ImmutableRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * Read-only view of the audit log. Rows are written only by App\Services\AuditLogger.
 *
 * @property int $id
 * @property int $sequence
 * @property string $occurred_at
 * @property string $category
 * @property string $action
 * @property string $severity
 * @property int|null $actor_id
 * @property string|null $actor_label
 * @property string|null $actor_role
 * @property string|null $subject_type
 * @property string|null $subject_id
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string|null $request_id
 * @property string|null $http_method
 * @property string|null $route
 * @property int|null $status_code
 * @property string|null $payload
 * @property string $previous_hash
 * @property string $hash
 */
class AuditLog extends Model
{
    use ImmutableRecord;

    public const HASHED_COLUMNS = [
        'occurred_at', 'category', 'action', 'severity', 'actor_id', 'actor_label', 'actor_role',
        'subject_type', 'subject_id', 'ip_address', 'user_agent', 'request_id', 'http_method',
        'route', 'status_code', 'payload',
    ];

    public $timestamps = false;
}
