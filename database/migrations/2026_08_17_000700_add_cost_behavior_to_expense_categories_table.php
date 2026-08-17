<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expense_categories', function (Blueprint $table) {
            $table->string('cost_behavior', 20)->default('FIXED');
        });

        DB::table('expense_categories')
            ->whereIn('name', ['Utilitas', 'Kebersihan'])
            ->update(['cost_behavior'=>'VARIABLE']);
    }

    public function down(): void
    {
        Schema::table('expense_categories', function (Blueprint $table) {
            $table->dropColumn('cost_behavior');
        });
    }
};
