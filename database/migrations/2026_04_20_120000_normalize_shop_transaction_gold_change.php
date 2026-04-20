<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('shop_transactions')
            ->where('type', 'purchase')
            ->update([
                'gold_change' => DB::raw('-ABS(gold_change)'),
            ]);

        DB::table('shop_transactions')
            ->where('type', 'consume_unlock')
            ->update([
                'gold_change' => 0,
            ]);
    }

    public function down(): void
    {
        // Irreversible normalization: previous invalid values are intentionally not restored.
    }
};
