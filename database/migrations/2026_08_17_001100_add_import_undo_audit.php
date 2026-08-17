<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_batches', function (Blueprint $table) {
            $table->unsignedInteger('undo_count')->default(0);
            $table->timestamp('last_undone_at')->nullable();
            $table->foreignId('last_undone_by')->nullable();
            $table->foreign('last_undone_by')->references('id')->on('users')->nullOnDelete();
            $table->index('last_undone_by', 'import_batches_last_undone_by_idx');
        });
    }

    public function down(): void
    {
        Schema::table('import_batches', function (Blueprint $table) {
            $table->dropForeign(['last_undone_by']);
            $table->dropIndex('import_batches_last_undone_by_idx');
            $table->dropColumn(['undo_count', 'last_undone_at', 'last_undone_by']);
        });
    }
};
