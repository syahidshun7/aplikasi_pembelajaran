<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('shop_items')->updateOrInsert(
            ['code' => 'RETAKE_TICKET'],
            [
                'name' => 'Retake Ticket',
                'description' => 'Membuka satu attempt tambahan untuk quest yang sudah dinilai.',
                'price_gold' => 1000,
                'is_active' => true,
                'is_stackable' => true,
                'deleted_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('shop_items')->where('code', 'RETAKE_TICKET')->delete();
    }
};
