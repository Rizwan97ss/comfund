<?php

namespace App\Concerns;

use LogicException;

/**
 * Refuses updates and deletes at the model layer. Database triggers back this up.
 */
trait ImmutableRecord
{
    public static function bootImmutableRecord(): void
    {
        static::updating(fn () => throw new LogicException(static::class.' records are immutable.'));
        static::deleting(fn () => throw new LogicException(static::class.' records are immutable.'));
    }
}
