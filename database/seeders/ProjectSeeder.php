<?php

namespace Database\Seeders;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProjectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
            DB::table('projects')->insert([
        [
                'title' => 'Website Perusahaan',
                'description' => 'Website perusahaan untuk profil dan informasi layanan.',
                'image' => 'https://via.placeholder.com/300',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Aplikasi Kasir',
                'description' => 'Aplikasi kasir sederhana untuk UMKM.',
                'image' => 'https://via.placeholder.com/300',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Sistem Informasi Akademik',
                'description' => 'Sistem informasi akademik untuk sekolah.',
                'image' => 'https://via.placeholder.com/300',
                'created_at' => now(),
                'updated_at' => now(),
            ],
    ]);

    }
}
