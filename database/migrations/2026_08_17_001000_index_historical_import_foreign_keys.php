<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_batches', fn (Blueprint $table) => $table->index('uploaded_by', 'import_batches_uploaded_by_idx'));
        Schema::table('payments', fn (Blueprint $table) => $table->index('import_batch_id', 'payments_import_batch_id_idx'));
        Schema::table('expenses', fn (Blueprint $table) => $table->index('import_batch_id', 'expenses_import_batch_id_idx'));
        Schema::table('import_rows', function (Blueprint $table) {
            $table->index('tenant_id', 'import_rows_tenant_id_idx');
            $table->index('imported_payment_id', 'import_rows_payment_id_idx');
            $table->index('imported_expense_id', 'import_rows_expense_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('import_rows', function (Blueprint $table) {
            $table->dropIndex('import_rows_tenant_id_idx');
            $table->dropIndex('import_rows_payment_id_idx');
            $table->dropIndex('import_rows_expense_id_idx');
        });
        Schema::table('expenses', fn (Blueprint $table) => $table->dropIndex('expenses_import_batch_id_idx'));
        Schema::table('payments', fn (Blueprint $table) => $table->dropIndex('payments_import_batch_id_idx'));
        Schema::table('import_batches', fn (Blueprint $table) => $table->dropIndex('import_batches_uploaded_by_idx'));
    }
};
