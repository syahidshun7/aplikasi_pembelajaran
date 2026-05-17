<?php

use App\Models\Creation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('creations', function (Blueprint $table) {
            $table->string('slug', 180)->nullable()->after('title');
        });

        $this->backfillSlugs();

        Schema::table('creations', function (Blueprint $table) {
            $table->unique('slug');
        });
    }

    public function down(): void
    {
        Schema::table('creations', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }

    private function backfillSlugs(): void
    {
        $creations = DB::table('creations')->select('id', 'title')->get();
        $usedSlugs = [];

        foreach ($creations as $creation) {
            $baseSlug = Str::slug((string) ($creation->title ?? '')) ?: 'creation';
            $slug = $baseSlug;
            $suffix = 2;

            while (
                in_array($slug, $usedSlugs, true)
                || DB::table('creations')
                    ->where('slug', $slug)
                    ->where('id', '!=', $creation->id)
                    ->exists()
            ) {
                $slug = $baseSlug . '-' . $suffix;
                $suffix++;
            }

            DB::table('creations')
                ->where('id', $creation->id)
                ->update(['slug' => $slug]);

            $usedSlugs[] = $slug;
        }
    }
};
