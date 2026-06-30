<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabel buku logbook (misal: "PKL PT. ABC", "PKL PT. XYZ")
        Schema::create('dooplab_logbooks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('mentor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index('owner_user_id', 'dooplab_logbooks_owner_idx');
        });

        // Tabel entri harian dalam sebuah logbook
        Schema::create('dooplab_logbook_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('logbook_id')->constrained('dooplab_logbooks')->cascadeOnDelete();
            $table->foreignId('todo_id')->nullable()->constrained('dooplab_todos')->nullOnDelete();
            $table->date('activity_date');
            $table->time('activity_time')->nullable();
            $table->string('activity', 500);
            $table->text('purpose')->nullable();
            $table->text('result')->nullable();
            $table->string('mentor_signature', 255)->nullable();
            $table->string('documentation_path')->nullable();
            $table->timestamps();

            $table->index(['logbook_id', 'activity_date'], 'dooplab_logbook_entries_logbook_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dooplab_logbook_entries');
        Schema::dropIfExists('dooplab_logbooks');
    }
};
