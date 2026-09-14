<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_check_in_codes', function (Blueprint $table) {
            if (! Schema::hasColumn('event_check_in_codes', 'plain_code')) {
                $table->text('plain_code')->nullable()->after('code_hash');
            }
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE event_attendances MODIFY status ENUM('pending','present','absent','excused','sick') NOT NULL DEFAULT 'pending'");
        }
    }

    public function down(): void
    {
        DB::table('event_attendances')
            ->where('status', 'sick')
            ->update(['status' => 'excused']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE event_attendances MODIFY status ENUM('pending','present','absent','excused') NOT NULL DEFAULT 'pending'");
        }

        Schema::table('event_check_in_codes', function (Blueprint $table) {
            if (Schema::hasColumn('event_check_in_codes', 'plain_code')) {
                $table->dropColumn('plain_code');
            }
        });
    }
};
