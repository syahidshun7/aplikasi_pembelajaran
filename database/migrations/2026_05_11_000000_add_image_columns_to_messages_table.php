<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table): void {
            if (! Schema::hasColumn('messages', 'message_type')) {
                $table->string('message_type', 20)->default('text')->after('message');
            }

            if (! Schema::hasColumn('messages', 'image_url')) {
                $table->string('image_url', 500)->nullable()->after('message_type');
            }

            if (! Schema::hasColumn('messages', 'image_width')) {
                $table->unsignedInteger('image_width')->nullable()->after('image_url');
            }

            if (! Schema::hasColumn('messages', 'image_height')) {
                $table->unsignedInteger('image_height')->nullable()->after('image_width');
            }

            if (! Schema::hasColumn('messages', 'image_size')) {
                $table->unsignedInteger('image_size')->nullable()->after('image_height');
            }
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table): void {
            foreach (['image_size', 'image_height', 'image_width', 'image_url', 'message_type'] as $column) {
                if (Schema::hasColumn('messages', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
