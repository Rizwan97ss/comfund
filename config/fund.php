<?php

return [
    /*
    | Business dates (months, due dates, "today") are computed in this timezone.
    | Timestamps are stored in UTC.
    */
    'timezone' => env('FUND_TIMEZONE', 'Asia/Kolkata'),

    /*
    | Proxies whose X-Forwarded headers are believed, as a comma-separated list or *.
    | Set this behind a load balancer, or the audit log will record the proxy's IP.
    */
    'trusted_proxies' => env('TRUSTED_PROXIES'),

    'invitation_ttl_days' => (int) env('FUND_INVITATION_TTL_DAYS', 7),

    'invitation_max_failed_attempts' => 5,

    /*
    | Largest single financial amount accepted, in paise (₹1 crore).
    */
    'max_amount_paise' => 1_000_000_000,

    'receipts' => [
        'max_kilobytes' => 5120,
        'mimes' => ['jpg', 'jpeg', 'png', 'webp', 'pdf'],
    ],

    /*
    | Audit log hash chain key. Keep it stable and separate from APP_KEY so that
    | rotating APP_KEY does not invalidate the chain.
    */
    'audit_hmac_key' => env('AUDIT_HMAC_KEY') ?: env('APP_KEY'),

    'backups' => [
        'key' => env('BACKUP_ENCRYPTION_KEY'),
        'directory' => 'backups',
        'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 30),
        'mysqldump_path' => env('MYSQLDUMP_PATH', 'mysqldump'),
    ],

    'demo_admin_email' => env('FUND_ADMIN_EMAIL', 'admin@example.com'),
];
