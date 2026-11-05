<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Installs database triggers that make a table append-only, so that tampering is
 * refused by the database itself and not only by the application.
 */
final class ImmutableTable
{
    /**
     * Refuse every UPDATE and DELETE on the given table.
     *
     * @param  literal-string  $table
     */
    public static function protect(string $table): void
    {
        self::rejectUpdatesWhen($table, null);
        self::rejectDeletes($table);
    }

    /**
     * Refuse UPDATEs matching the given SQL condition (OLD and NEW are available).
     * A null condition refuses every UPDATE. The condition is a trusted, static string.
     *
     * @param  literal-string  $table
     * @param  literal-string|null  $condition
     */
    public static function rejectUpdatesWhen(string $table, ?string $condition): void
    {
        self::create("{$table}_block_update", $table, 'UPDATE', $condition);
    }

    /**
     * @param  literal-string  $table
     */
    public static function rejectDeletes(string $table): void
    {
        self::create("{$table}_block_delete", $table, 'DELETE', null);
    }

    /**
     * @param  literal-string  $table
     */
    public static function release(string $table): void
    {
        DB::unprepared("DROP TRIGGER IF EXISTS {$table}_block_update");
        DB::unprepared("DROP TRIGGER IF EXISTS {$table}_block_delete");
    }

    /**
     * @param  literal-string  $name
     * @param  literal-string  $table
     * @param  'UPDATE'|'DELETE'  $operation
     * @param  literal-string|null  $condition
     */
    private static function create(string $name, string $table, string $operation, ?string $condition): void
    {
        $message = "{$table} is immutable";

        if (DB::getDriverName() === 'sqlite') {
            $when = $condition === null ? '' : "WHEN {$condition}";

            DB::unprepared("CREATE TRIGGER {$name} BEFORE {$operation} ON {$table} FOR EACH ROW {$when} BEGIN SELECT RAISE(ABORT, '{$message}'); END");

            return;
        }

        $signal = "SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$message}';";
        $body = $condition === null ? $signal : "IF {$condition} THEN {$signal} END IF;";

        DB::unprepared("CREATE TRIGGER {$name} BEFORE {$operation} ON {$table} FOR EACH ROW BEGIN {$body} END");
    }
}
