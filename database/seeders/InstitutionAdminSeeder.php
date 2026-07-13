<?php

namespace Database\Seeders;

use App\Models\Institution;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class InstitutionAdminSeeder extends Seeder
{
    /**
     * Seed an institution admin user for each institution.
     *
     * Login emails use the institution's short_name (e.g. cbu, unza, mu)
     * for memorability:
     *   email:    admin@{short_name}.zm
     *   password: password123
     *
     * In production, institution admins would be provisioned individually
     * by ZamAdmit staff after a partnership agreement.
     */
    public function run(): void
    {
        $institutions = Institution::all();

        foreach ($institutions as $institution) {
            $shortName = strtolower($institution->short_name);
            $email     = "admin@{$shortName}.zm";

            User::updateOrCreate(
                ['email' => $email],
                [
                    'name'                 => "{$institution->short_name} Admin",
                    'first_name'           => $institution->short_name,
                    'last_name'            => 'Admin',
                    'password'             => Hash::make('password123'),
                    'role'                 => 'institution_admin',
                    'institution_id'       => $institution->id,
                    'profile_complete'     => true,
                    'must_change_password' => true,
                    'password_changed_at'  => null,
                ]
            );
        }

        $this->command->info('Institution admins seeded successfully.');
        $this->command->info('Login emails: admin@{short_name}.zm   Password: password123');
    }
}