<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
/**
 * Bootstraps a ZamAdmit platform administrator.
 *
 * This is deliberately a console command, not a web endpoint: platform-level
 * account creation must not be reachable from the public internet. It is the
 * root-account bootstrap — every subsequent platform admin can be created
 * from the portal by an existing one.
 */
class CreatePlatformAdmin extends Command
{
    protected $signature = 'zamadmit:create-platform-admin
                            {--email= : Email address}
                            {--name= : Full name}';

    protected $description = 'Create a ZamAdmit platform administrator account';

    public function handle(): int
    {
        $email = $this->option('email') ?: $this->ask('Email address');
        $name  = $this->option('name')  ?: $this->ask('Full name');

        if (User::where('email', $email)->exists()) {
            $this->error("A user with the email {$email} already exists.");
            return self::FAILURE;
        }

        $parts     = preg_split('/\s+/', trim($name), 2);
        $firstName = $parts[0] ?? 'Platform';
        $lastName  = $parts[1] ?? 'Admin';

        $password = Str::password(16);

        $user = User::create([
            'first_name'           => $firstName,
            'last_name'            => $lastName,
            'name'                 => $name,
            'email'                => $email,
            'password'             => Hash::make($password),
            'role'                 => 'platform_admin',
            'admin_role'           => null,
            'institution_id'       => null,
            'must_change_password' => true,
            'profile_complete'     => true,
        ]);

        $this->newLine();
        $this->info('Platform administrator created.');
        $this->table(['Field', 'Value'], [
            ['Name',     $name],
            ['Email',    $email],
            ['Password', $password],
            ['User ID',  $user->id],
        ]);
        $this->warn('Store this password securely. It is shown once and must be changed at first sign-in.');

        return self::SUCCESS;
    }
}