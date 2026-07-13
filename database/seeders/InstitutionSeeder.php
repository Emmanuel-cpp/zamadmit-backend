<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Institution;

class InstitutionSeeder extends Seeder
{
    public function run(): void
    {
        $institutions = [
            [
                'slug'                      => 'copperbelt-university',
                'name'                      => 'Copperbelt University',
                'short_name'                => 'CBU',
                'type'                      => 'public',
                'city'                      => 'Kitwe',
                'province'                  => 'Copperbelt',
                'description'               => 'A leading public university in Zambia with strong programmes in engineering, business, and the natural sciences.',
                'established'               => 1987,
                'application_deadline'      => '2025-06-30',
                'is_accepting_applications' => true,
                'image_url'                 => '/images/institutions/cbu.jpg',
            ],
            [
                'slug'                      => 'university-of-zambia',
                'name'                      => 'University of Zambia',
                'short_name'                => 'UNZA',
                'type'                      => 'public',
                'city'                      => 'Lusaka',
                'province'                  => 'Lusaka',
                'description'               => 'Zambia\'s flagship public university, offering the widest range of academic programmes in the country.',
                'established'               => 1965,
                'application_deadline'      => '2025-05-31',
                'is_accepting_applications' => true,
                'image_url'                 => '/images/institutions/unza.jpg',
            ],
            [
                'slug'                      => 'mulungushi-university',
                'name'                      => 'Mulungushi University',
                'short_name'                => 'MU',
                'type'                      => 'public',
                'city'                      => 'Kabwe',
                'province'                  => 'Central',
                'description'               => 'A modern public university known for its business, education, and ICT programmes.',
                'established'               => 2008,
                'application_deadline'      => '2025-06-15',
                'is_accepting_applications' => true,
                'image_url'                 => '/images/institutions/mu.jpg',
            ],
            [
                'slug'                      => 'zambia-open-university',
                'name'                      => 'Zambia Open University',
                'short_name'                => 'ZAOU',
                'type'                      => 'public',
                'city'                      => 'Lusaka',
                'province'                  => 'Lusaka',
                'description'               => 'A distance-learning institution providing flexible higher education across Zambia.',
                'established'               => 2002,
                'application_deadline'      => '2025-07-31',
                'is_accepting_applications' => true,
                'image_url'                 => '/images/institutions/zaou.jpg',
            ],
            [
                'slug'                      => 'northrise-university',
                'name'                      => 'Northrise University',
                'short_name'                => 'NU',
                'type'                      => 'private',
                'city'                      => 'Ndola',
                'province'                  => 'Copperbelt',
                'description'               => 'A private faith-based university focused on technology, business, and health sciences.',
                'established'               => 2004,
                'application_deadline'      => '2025-06-30',
                'is_accepting_applications' => true,
                'image_url'                 => '/images/institutions/nu.jpg',
            ],
            [
                'slug'                      => 'cavendish-university-zambia',
                'name'                      => 'Cavendish University Zambia',
                'short_name'                => 'CUZ',
                'type'                      => 'private',
                'city'                      => 'Lusaka',
                'province'                  => 'Lusaka',
                'description'               => 'A private university offering professional programmes in business, ICT, and law.',
                'established'               => 2004,
                'application_deadline'      => '2025-08-15',
                'is_accepting_applications' => false,
                'image_url'                 => '/images/institutions/cuz.jpg',
            ],
        ];

        foreach ($institutions as $data) {
            Institution::create($data);
        }
    }
}