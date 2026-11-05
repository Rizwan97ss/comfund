<?php

namespace App\Concerns;

use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;

/**
 * Records every create, update and delete of the model in the audit log, with
 * before and after values. Secrets are redacted by the logger.
 */
trait AuditsChanges
{
    public static function bootAuditsChanges(): void
    {
        static::created(function (self $model): void {
            app(AuditLogger::class)->record(
                action: $model->getTable().'.created',
                category: $model->auditCategory(),
                subject: $model,
                payload: ['after' => $model->getAttributes()],
            );
        });

        static::updated(function (self $model): void {
            $changes = array_diff_key($model->getChanges(), ['updated_at' => true]);

            if ($changes === []) {
                return;
            }

            app(AuditLogger::class)->record(
                action: $model->getTable().'.updated',
                category: $model->auditCategory(),
                subject: $model,
                payload: [
                    'before' => array_intersect_key($model->getPrevious(), $changes),
                    'after' => $changes,
                ],
            );
        });

        static::deleted(function (self $model): void {
            app(AuditLogger::class)->record(
                action: $model->getTable().'.deleted',
                category: $model->auditCategory(),
                severity: 'warning',
                subject: $model,
                payload: ['before' => $model->getAttributes()],
            );
        });
    }

    public function auditCategory(): string
    {
        return 'data';
    }
}
