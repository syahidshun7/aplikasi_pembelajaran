<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   public function up(): void
{
    Schema::table('users', function (Blueprint $table) {
        // 1. Buat kolom tanpa unique dulu
        $table->string('username')->nullable()->after('name');
    });

    // 2. Isi data username sementara (misal disamakan dengan email/id)
    // Ini agar tidak ada data kosong yang dianggap duplikat
    \App\Models\User::all()->each(function ($user) {
        $user->update(['username' => 'user_' . $user->id]);
    });

    Schema::table('users', function (Blueprint $table) {
        // 3. Baru tambahkan index unique
        $table->unique('username');
    });
}
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
           $table->dropColumn(['username', 'profile_photo']);
        });
    }
};
