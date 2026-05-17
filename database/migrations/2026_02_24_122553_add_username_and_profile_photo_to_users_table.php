<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Tahap Pertama: Buat kolom sebagai Nullable agar tidak ada error "Default Value"
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'username')) {
                $table->string('username')->nullable()->after('name');
            }
            if (!Schema::hasColumn('users', 'profile_photo')) {
                $table->string('profile_photo')->nullable()->after('username');
            }
        });

        // 2. Tahap Kedua: Isi data username untuk User yang sudah ada
        // Kita gunakan ID agar dijamin unik dan tidak error saat penambahan Index Unique nanti
        DB::table('users')
            ->where(function ($query) {
                $query->whereNull('username')
                    ->orWhere('username', '');
            })
            ->orderBy('id')
            ->get(['id'])
            ->each(function ($user) {
                DB::table('users')
                    ->where('id', $user->id)
                    ->update([
                        'username' => 'player_' . $user->id . rand(10, 99),
                    ]);
            });

        // 3. Tahap Ketiga: Tambahkan Index Unique pada kolom username
        Schema::table('users', function (Blueprint $table) {
            // Kita bungkus dalam try-catch supaya jika index sudah ada, migrasi tidak berhenti (crash)
            try {
                $table->unique('username');
            } catch (\Exception $e) {
                // Index sudah ada, abaikan errornya
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'username')) {
                $table->dropUnique(['username']);
                $table->dropColumn('username');
            }
            if (Schema::hasColumn('users', 'profile_photo')) {
                $table->dropColumn('profile_photo');
            }
        });
    }
};
