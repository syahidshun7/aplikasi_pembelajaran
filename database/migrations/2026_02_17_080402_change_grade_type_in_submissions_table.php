<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   public function up(): void
{
    Schema::table('submissions', function (Blueprint $table) {
        // Kita ubah ke integer, default 0
        $table->integer('grade')->default(0)->change();
        
        // Tambahkan kolom json untuk rincian checkbox
        $table->json('scores_detail')->nullable()->after('grade');
    });
}

public function down(): void
{
    Schema::table('submissions', function (Blueprint $table) {
        $table->string('grade')->nullable()->change();
        $table->dropColumn('scores_detail');
    });
}
};
