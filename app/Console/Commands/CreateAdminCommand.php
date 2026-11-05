<?php

namespace App\Console\Commands;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class CreateAdminCommand extends Command
{
    protected $signature = 'fund:create-admin';

    protected $description = 'Create an administrator. Self-registration only creates members, so this is how the first admin is made.';

    public function handle(): int
    {
        $data = [
            'name' => text('Full name', required: true),
            'email' => text('Email', required: true),
            'password' => password('Password', required: true),
        ];

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::defaults()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        (new User($data))->forceFill([
            'role' => UserRole::Admin,
            'status' => AccountStatus::Active,
            'email_verified_at' => now(),
            'activated_at' => now(),
        ])->save();

        $this->info('Administrator created.');

        return self::SUCCESS;
    }
}
