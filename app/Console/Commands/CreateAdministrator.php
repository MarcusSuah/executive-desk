<?php

namespace App\Console\Commands;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\Role;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\AppSettings;
use App\Support\RolesAndPermissions;
use App\Support\Timezones;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

#[Signature('app:create-admin {--name= : Full name} {--email= : Email address} {--timezone= : IANA time zone}')]
#[Description('Create the initial system administrator (the password is entered interactively and never echoed)')]
class CreateAdministrator extends Command
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Execute the console command.
     */
    public function handle(AuditLogger $audit): int
    {
        RolesAndPermissions::sync();

        $input = [
            'name' => $this->option('name') ?? text('Full name', required: true),
            'email' => $this->option('email') ?? text('Email address', required: true),
            'timezone' => $this->option('timezone') ?? text('Time zone (IANA, e.g. Asia/Manila)', default: AppSettings::defaultTimezone(), required: true),
            'password' => password('Password (min. 12 characters)', required: true),
        ];
        $input['password_confirmation'] = password('Confirm password', required: true);

        $validator = Validator::make($input, [
            ...$this->profileRules(),
            'timezone' => ['required', Rule::in(Timezones::identifiers())],
            'password' => [...$this->passwordRules(), 'min:12'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $user = DB::transaction(function () use ($input, $audit): User {
            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'timezone' => $input['timezone'],
                'password' => $input['password'],
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();
            $user->assignRole(Role::Administrator->value);

            $audit->log('user.created', $user, [], ['email' => $user->email, 'roles' => [Role::Administrator->value], 'source' => 'console']);

            return $user;
        });

        $this->components->info("Administrator {$user->email} created. Sign in and assign further roles from User administration.");

        return self::SUCCESS;
    }
}
