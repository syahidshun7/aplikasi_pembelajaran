<?php

namespace Database\Seeders;

use App\Models\JobRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class JobRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $jobs = [
            ['name' => 'Software Developer', 'description' => 'Fokus pada pengembangan aplikasi dan sistem perangkat lunak.'],
            ['name' => 'Frontend Developer', 'description' => 'Fokus pada antarmuka pengguna dan pengalaman web interaktif.'],
            ['name' => 'Backend Developer', 'description' => 'Fokus pada server, database, API, dan logika bisnis.'],
            ['name' => 'UI/UX Designer', 'description' => 'Fokus pada riset, desain antarmuka, dan pengalaman pengguna.'],
            ['name' => 'Data Analyst', 'description' => 'Fokus pada analisis data, visualisasi, dan insight bisnis.'],
            ['name' => 'DevOps Engineer', 'description' => 'Fokus pada deployment, infrastruktur, automation, dan reliability.'],
            ['name' => 'QA Engineer', 'description' => 'Fokus pada quality assurance, testing manual dan otomatisasi.'],
        ];

        foreach ($jobs as $job) {
            JobRole::query()->updateOrCreate(
                ['slug' => Str::slug($job['name'])],
                $job + ['slug' => Str::slug($job['name'])]
            );
        }
    }
}

