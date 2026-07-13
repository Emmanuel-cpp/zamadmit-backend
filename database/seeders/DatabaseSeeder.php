<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Order matters — institutions must exist before programmes
        $this->call([
            InstitutionSeeder::class,
            ProgrammeSeeder::class,
            InstitutionAdminSeeder::class, 
        ]);
        
    }
}