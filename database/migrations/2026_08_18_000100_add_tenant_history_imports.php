<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->foreignId('import_batch_id')->nullable()->constrained('import_batches')->nullOnDelete();
            $table->index('import_batch_id', 'tenants_import_batch_id_idx');
        });

        Schema::table('import_rows', function (Blueprint $table) {
            $table->foreignId('room_id')->nullable()->constrained('rooms')->restrictOnDelete();
            $table->string('tenant_name', 120)->nullable();
            $table->string('tenant_phone', 30)->nullable();
            $table->string('tenant_identity_number', 40)->nullable();
            $table->date('tenant_move_in')->nullable();
            $table->date('tenant_move_out')->nullable();
            $table->foreignId('imported_tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->index('room_id', 'import_rows_room_id_idx');
            $table->index('imported_tenant_id', 'import_rows_imported_tenant_id_idx');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE import_rows DROP CONSTRAINT IF EXISTS import_rows_type_valid');
            DB::statement("ALTER TABLE import_rows ADD CONSTRAINT import_rows_type_valid CHECK (transaction_type IN ('PAYMENT','EXPENSE','TENANT'))");
            DB::statement('ALTER TABLE import_rows ADD CONSTRAINT import_rows_tenant_dates_valid CHECK (tenant_move_out IS NULL OR tenant_move_in IS NULL OR tenant_move_out >= tenant_move_in)');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE import_rows DROP CONSTRAINT IF EXISTS import_rows_tenant_dates_valid');
            DB::statement('ALTER TABLE import_rows DROP CONSTRAINT IF EXISTS import_rows_type_valid');
            DB::statement("ALTER TABLE import_rows ADD CONSTRAINT import_rows_type_valid CHECK (transaction_type IN ('PAYMENT','EXPENSE'))");
        }

        Schema::table('import_rows', function (Blueprint $table) {
            $table->dropForeign(['room_id']);
            $table->dropForeign(['imported_tenant_id']);
            $table->dropIndex('import_rows_room_id_idx');
            $table->dropIndex('import_rows_imported_tenant_id_idx');
            $table->dropColumn(['room_id', 'tenant_name', 'tenant_phone', 'tenant_identity_number', 'tenant_move_in', 'tenant_move_out', 'imported_tenant_id']);
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropForeign(['import_batch_id']);
            $table->dropIndex('tenants_import_batch_id_idx');
            $table->dropColumn('import_batch_id');
        });
    }
};
