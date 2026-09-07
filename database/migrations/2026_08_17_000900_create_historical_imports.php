<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('source_type', 12);
            $table->json('original_names')->nullable();
            $table->string('status', 12)->default('DRAFT')->index();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('valid_rows')->default(0);
            $table->unsignedInteger('imported_rows')->default(0);
            $table->text('error_message')->nullable();
            $table->foreignId('uploaded_by')->index()->constrained('users')->restrictOnDelete();
            $table->timestamp('committed_at')->nullable();
            $table->timestamps();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->boolean('is_historical')->default(false)->index();
            $table->date('coverage_start')->nullable()->index();
            $table->date('coverage_end')->nullable();
            $table->foreignId('import_batch_id')->nullable()->index()->constrained('import_batches')->nullOnDelete();
        });
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('import_batch_id')->nullable()->index()->constrained('import_batches')->nullOnDelete();
        });

        Schema::create('import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_batch_id')->constrained('import_batches')->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->boolean('selected')->default(true);
            $table->string('transaction_type', 12)->default('PAYMENT');
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->restrictOnDelete();
            $table->string('expense_category', 60)->nullable();
            $table->date('transaction_date')->nullable();
            $table->decimal('amount', 15, 2)->nullable();
            $table->string('billing_cycle', 12)->nullable();
            $table->unsignedSmallInteger('period_count')->default(1);
            $table->date('period_start')->nullable();
            $table->string('method', 12)->nullable();
            $table->string('title', 150)->nullable();
            $table->text('notes')->nullable();
            $table->decimal('confidence', 5, 2)->nullable();
            $table->json('validation_errors')->nullable();
            $table->json('raw_data')->nullable();
            $table->foreignId('imported_payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->foreignId('imported_expense_id')->nullable()->constrained('expenses')->nullOnDelete();
            $table->timestamps();
            $table->unique(['import_batch_id', 'row_number']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE import_batches ADD CONSTRAINT import_batches_source_valid CHECK (source_type IN ('IMAGE','CSV'))");
            DB::statement("ALTER TABLE import_batches ADD CONSTRAINT import_batches_status_valid CHECK (status IN ('DRAFT','COMPLETED','FAILED'))");
            DB::statement("ALTER TABLE import_rows ADD CONSTRAINT import_rows_type_valid CHECK (transaction_type IN ('PAYMENT','EXPENSE'))");
            DB::statement("ALTER TABLE import_rows ADD CONSTRAINT import_rows_cycle_valid CHECK (billing_cycle IS NULL OR billing_cycle IN ('DAILY','WEEKLY','MONTHLY'))");
            DB::statement("ALTER TABLE import_rows ADD CONSTRAINT import_rows_method_valid CHECK (method IS NULL OR method IN ('Transfer','Cash','QRIS'))");
            DB::statement('ALTER TABLE import_rows ADD CONSTRAINT import_rows_amount_positive CHECK (amount IS NULL OR amount > 0)');
            DB::statement('ALTER TABLE import_rows ADD CONSTRAINT import_rows_period_positive CHECK (period_count > 0)');
            DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_coverage_order CHECK (coverage_end IS NULL OR coverage_start IS NULL OR coverage_end >= coverage_start)');
            foreach (['import_batches', 'import_rows'] as $table) {
                DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
                DB::statement("REVOKE ALL ON TABLE {$table} FROM anon, authenticated");
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('import_rows');
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE payments DROP CONSTRAINT IF EXISTS payments_coverage_order');
        }
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('import_batch_id');
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('import_batch_id');
            $table->dropIndex(['is_historical']);
            $table->dropIndex(['coverage_start']);
            $table->dropColumn(['is_historical', 'coverage_start', 'coverage_end']);
        });
        Schema::dropIfExists('import_batches');
    }
};
