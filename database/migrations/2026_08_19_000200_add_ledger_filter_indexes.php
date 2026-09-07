<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->index(['paid_at', 'id'], 'payments_paid_at_id_idx');
            $table->index('amount', 'payments_amount_idx');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->index(['spent_at', 'id'], 'expenses_spent_at_id_idx');
            $table->index('amount', 'expenses_amount_idx');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_paid_at_id_idx');
            $table->dropIndex('payments_amount_idx');
            $table->index('paid_at', 'payments_paid_at_idx');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropIndex('expenses_spent_at_id_idx');
            $table->dropIndex('expenses_amount_idx');
            $table->index('spent_at', 'expenses_spent_at_idx');
        });
    }
};
