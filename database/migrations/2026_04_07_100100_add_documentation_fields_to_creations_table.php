<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('creations', function (Blueprint $table) {
            $table->foreignId('category_id')
                ->nullable()
                ->after('category')
                ->constrained('creation_categories')
                ->nullOnDelete();
            $table->longText('content')->nullable()->after('description');
            $table->json('tags')->nullable()->after('category_id');
            $table->string('featured_image')->nullable()->after('tags');
            $table->string('publication_status', 20)->default('publish')->after('featured_image');
        });

        DB::table('creations')
            ->whereNull('content')
            ->update([
                'content' => DB::raw('description'),
                'publication_status' => DB::raw("CASE WHEN is_public = 1 THEN 'publish' ELSE 'draft' END"),
            ]);
    }

    public function down(): void
    {
        Schema::table('creations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
            $table->dropColumn(['content', 'tags', 'featured_image', 'publication_status']);
        });
    }
};
