<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE media_files DROP CONSTRAINT IF EXISTS media_files_kind_valid');
            DB::statement("ALTER TABLE media_files ADD CONSTRAINT media_files_kind_valid CHECK (kind IN ('KTP','CATEGORY','HERO','LOGO','FAVICON'))");
        }
    }

    public function down(): void
    {
        DB::table('media_files')->whereIn('kind', ['LOGO', 'FAVICON'])->delete();

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE media_files DROP CONSTRAINT IF EXISTS media_files_kind_valid');
            DB::statement("ALTER TABLE media_files ADD CONSTRAINT media_files_kind_valid CHECK (kind IN ('KTP','CATEGORY','HERO'))");
        }
    }
};
