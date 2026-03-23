<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('group_user', 'deleted_at')) {
            Schema::table('group_user', function (Blueprint $table): void {
                $table->softDeletes();
                $table->index(['study_group_id', 'deleted_at'], 'group_user_group_deleted_at_index');
                $table->index(['user_id', 'deleted_at'], 'group_user_user_deleted_at_index');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('group_user', 'deleted_at')) {
            Schema::table('group_user', function (Blueprint $table): void {
                $table->dropIndex('group_user_group_deleted_at_index');
                $table->dropIndex('group_user_user_deleted_at_index');
                $table->dropSoftDeletes();
            });
        }
    }
};
