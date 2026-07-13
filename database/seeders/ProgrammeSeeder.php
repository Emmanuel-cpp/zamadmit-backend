<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Institution;
use App\Models\Programme;
use App\Models\ProgrammeRequirement;

class ProgrammeSeeder extends Seeder
{
    public function run(): void
    {
        $programmes = [
            [
                'institution' => 'copperbelt-university',
                'slug'         => 'cbu-bsc-computer-science',
                'name'         => 'BSc Computer Science',
                'qualification'=> 'Bachelor',
                'school'       => 'School of ICT',
                'duration_years'=> 4,
                'study_mode'   => 'Full-time',
                'intake'       => 'January 2026',
                'description'  => 'Four-year programme covering software engineering, data structures, AI, and systems design.',
                'requirements' => [
                    ['subject' => 'Mathematics',      'min_grade' => 4],
                    ['subject' => 'English Language',  'min_grade' => 5],
                    ['subject' => 'Physics',           'min_grade' => 5],
                ],
            ],
            [
                'institution' => 'copperbelt-university',
                'slug'         => 'cbu-beng-software-engineering',
                'name'         => 'BEng Software Engineering',
                'qualification'=> 'Bachelor',
                'school'       => 'School of Engineering',
                'duration_years'=> 4,
                'study_mode'   => 'Full-time',
                'intake'       => 'January 2026',
                'description'  => 'Engineering-focused degree blending mathematics, electronics, and large-scale software design.',
                'requirements' => [
                    ['subject' => 'Mathematics',      'min_grade' => 3],
                    ['subject' => 'Physics',           'min_grade' => 4],
                    ['subject' => 'English Language',  'min_grade' => 5],
                ],
            ],
            [
                'institution' => 'copperbelt-university',
                'slug'         => 'cbu-beng-mining-engineering',
                'name'         => 'BEng Mining Engineering',
                'qualification'=> 'Bachelor',
                'school'       => 'School of Mines',
                'duration_years'=> 5,
                'study_mode'   => 'Full-time',
                'intake'       => 'January 2026',
                'description'  => 'Five-year engineering programme rooted in the Copperbelt mining tradition.',
                'requirements' => [
                    ['subject' => 'Mathematics',      'min_grade' => 3],
                    ['subject' => 'Physics',           'min_grade' => 3],
                    ['subject' => 'Chemistry',         'min_grade' => 4],
                ],
            ],
            [
                'institution' => 'university-of-zambia',
                'slug'         => 'unza-ba-economics',
                'name'         => 'BA Economics',
                'qualification'=> 'Bachelor',
                'school'       => 'School of Humanities & Social Sciences',
                'duration_years'=> 4,
                'study_mode'   => 'Full-time',
                'intake'       => 'January 2026',
                'description'  => 'Comprehensive economics programme covering micro, macro, econometrics, and development economics.',
                'requirements' => [
                    ['subject' => 'Mathematics',      'min_grade' => 4],
                    ['subject' => 'English Language',  'min_grade' => 5],
                ],
            ],
            [
                'institution' => 'university-of-zambia',
                'slug'         => 'unza-llb',
                'name'         => 'LLB Bachelor of Laws',
                'qualification'=> 'Bachelor',
                'school'       => 'School of Law',
                'duration_years'=> 4,
                'study_mode'   => 'Full-time',
                'intake'       => 'January 2026',
                'description'  => 'Foundational law degree leading to admission to the Zambian Bar.',
                'requirements' => [
                    ['subject' => 'English Language',  'min_grade' => 3],
                    ['subject' => 'Mathematics',       'min_grade' => 6],
                ],
            ],
            [
                'institution' => 'university-of-zambia',
                'slug'         => 'unza-mbchb-medicine',
                'name'         => 'MBChB Medicine and Surgery',
                'qualification'=> 'Bachelor',
                'school'       => 'School of Medicine',
                'duration_years'=> 7,
                'study_mode'   => 'Full-time',
                'intake'       => 'January 2026',
                'description'  => 'Seven-year medical doctor programme with strong clinical placements.',
                'requirements' => [
                    ['subject' => 'Biology',          'min_grade' => 2],
                    ['subject' => 'Chemistry',         'min_grade' => 2],
                    ['subject' => 'Mathematics',       'min_grade' => 3],
                    ['subject' => 'English Language',  'min_grade' => 5],
                ],
            ],
            [
                'institution' => 'mulungushi-university',
                'slug'         => 'mu-bcom-accounting',
                'name'         => 'BCom Accounting',
                'qualification'=> 'Bachelor',
                'school'       => 'School of Business',
                'duration_years'=> 4,
                'study_mode'   => 'Full-time',
                'intake'       => 'January 2026',
                'description'  => 'Professional accounting degree aligned with ZICA and ACCA pathways.',
                'requirements' => [
                    ['subject' => 'Mathematics',      'min_grade' => 5],
                    ['subject' => 'English Language',  'min_grade' => 5],
                ],
            ],
            [
                'institution' => 'mulungushi-university',
                'slug'         => 'mu-bed-secondary',
                'name'         => 'BEd Secondary Education',
                'qualification'=> 'Bachelor',
                'school'       => 'School of Education',
                'duration_years'=> 4,
                'study_mode'   => 'Full-time',
                'intake'       => 'January 2026',
                'description'  => 'Train as a secondary school teacher with a focus on STEM subjects.',
                'requirements' => [
                    ['subject' => 'English Language',  'min_grade' => 5],
                    ['subject' => 'Mathematics',       'min_grade' => 6],
                ],
            ],
            [
                'institution' => 'zambia-open-university',
                'slug'         => 'zaou-diploma-it',
                'name'         => 'Diploma in Information Technology',
                'qualification'=> 'Diploma',
                'school'       => 'School of ICT',
                'duration_years'=> 3,
                'study_mode'   => 'Distance',
                'intake'       => 'January 2026',
                'description'  => 'Distance-learning IT diploma — study from anywhere in Zambia.',
                'requirements' => [
                    ['subject' => 'Mathematics',      'min_grade' => 6],
                    ['subject' => 'English Language',  'min_grade' => 6],
                ],
            ],
            [
                'institution' => 'northrise-university',
                'slug'         => 'nu-bsc-nursing',
                'name'         => 'BSc Nursing',
                'qualification'=> 'Bachelor',
                'school'       => 'Faculty of Health Sciences',
                'duration_years'=> 4,
                'study_mode'   => 'Full-time',
                'intake'       => 'January 2026',
                'description'  => 'Four-year nursing programme with hospital placements.',
                'requirements' => [
                    ['subject' => 'Biology',          'min_grade' => 4],
                    ['subject' => 'Chemistry',         'min_grade' => 5],
                    ['subject' => 'English Language',  'min_grade' => 5],
                ],
            ],
        ];

        foreach ($programmes as $data) {
            $institution = Institution::where('slug', $data['institution'])->first();

            if (!$institution) continue;

            $programme = Programme::create([
                'institution_id' => $institution->id,
                'slug'           => $data['slug'],
                'name'           => $data['name'],
                'qualification'  => $data['qualification'],
                'school'         => $data['school'],
                'duration_years' => $data['duration_years'],
                'study_mode'     => $data['study_mode'],
                'intake'         => $data['intake'],
                'description'    => $data['description'],
            ]);

            foreach ($data['requirements'] as $req) {
                ProgrammeRequirement::create([
                    'programme_id' => $programme->id,
                    'subject'      => $req['subject'],
                    'min_grade'    => $req['min_grade'],
                ]);
            }
        }
    }
}