<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_files', function (Blueprint $table) {
            $table->boolean('is_thumbnail')->default(false)->after('position')->index();
        });

        DB::table('media_files')
            ->where('kind', 'CATEGORY')
            ->whereNotNull('room_category_id')
            ->selectRaw('room_category_id, MIN(id) AS thumbnail_id')
            ->groupBy('room_category_id')
            ->get()
            ->each(fn ($row) => DB::table('media_files')->where('id', $row->thumbnail_id)->update(['is_thumbnail' => true]));
    }

    public function down(): void
    {
        Schema::table('media_files', function (Blueprint $table) {
            $table->dropIndex(['is_thumbnail']);
            $table->dropColumn('is_thumbnail');
        });
    }
};
