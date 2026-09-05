<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_data_forms', function (Blueprint $table) {
            $table->index('validated_by');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_data_forms', function (Blueprint $table) {
            $table->dropIndex(['validated_by']);
        });
    }
};
