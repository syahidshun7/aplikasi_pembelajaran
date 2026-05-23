<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Buat Akun Admin
        User::create([
            'name'     => 'Zoro Admin',
            'email'    => 'syahidshun7@gmail.com',
            'password' => Hash::make('12345678'), // Password untuk login
            'role'     => 'admin', // Pastikan kolom role sudah ada di tabel users
        ]);

        // 2. Buat Akun User Biasa (Adventurer)
    User::create([
            'name'     => 'Luffy Player',
            'email'    => 'player@guild.com',
            'password' => Hash::make('12345678'),
            'role'     => 'user',
        ]);

        // Opsional: Buat 10 user random untuk meramaikan Lobby
       
    }
}