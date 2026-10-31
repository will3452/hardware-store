<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\StoreAccess;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreatePlatformAdministrator extends Command
{
    protected $signature = 'app:create-admin';

    protected $description = 'Create the initial platform administrator using hidden password prompts';

    public function handle(): int
    {
        if (User::query()->whereHas('platformPermissions')->exists()) {
            $this->error('An administrator already exists. Use the reviewed account administration process.');

            return self::FAILURE;
        }

        $input = [
            'name' => $this->ask('Administrator name'),
            'email' => mb_strtolower(trim((string) $this->ask('Administrator email'))),
            'password' => $this->secret('Password'),
            'password_confirmation' => $this->secret('Confirm password'),
        ];

        $validator = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        DB::transaction(function () use ($input): void {
            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
            ]);

            $user->forceFill(['email_verified_at' => now()])->save();
            foreach (StoreAccess::PERMISSIONS as $permission) {
                $user->platformPermissions()->create(['permission' => $permission]);
            }
        });

        $this->info('Administrator created. Sign in and configure two-factor authentication before opening the Admin Portal.');

        return self::SUCCESS;
    }
}
