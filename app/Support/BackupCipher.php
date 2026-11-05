<?php

namespace App\Support;

use Illuminate\Encryption\Encrypter;

/**
 * Compresses and encrypts backups with a key that is separate from APP_KEY.
 */
final class BackupCipher
{
    public static function encrypt(string $plain): string
    {
        return self::encrypter()->encryptString((string) gzencode($plain, 9));
    }

    public static function decrypt(string $encrypted): string
    {
        return (string) gzdecode(self::encrypter()->decryptString($encrypted));
    }

    private static function encrypter(): Encrypter
    {
        $key = (string) config('fund.backups.key');

        return new Encrypter(
            base64_decode(str_starts_with($key, 'base64:') ? substr($key, 7) : $key),
            'aes-256-gcm',
        );
    }
}
