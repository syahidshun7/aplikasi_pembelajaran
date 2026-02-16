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
        Schema::create('submissions', function (Blueprint $table) {
            // Menghubungkan ke tabel quests
            $table->id();
            $table->foreignId('quest_id')->constrained()->onDelete('cascade');
            // Menghubungkan ke tabel users (yang mengerjakan)
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Isi laporan tugas (bisa berupa Link GitHub, teks, atau pesan)
            $table->text('content');

            // Status verifikasi dari Admin
            $table->enum('status', ['Pending', 'Approved', 'Rejected'])->default('Pending');

            // Catatan dari Admin (misal: alasan penolakan)
            $table->text('admin_notes')->nullable();
            // Di dalam file migrations create_submissions_table
            $table->string('file_path')->nullable(); // Menyimpan lokasi file

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('submissions');
    }
};
