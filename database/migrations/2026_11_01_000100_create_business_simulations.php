<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_simulations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->default('Simulasi Utama');
            $table->decimal('daily_occupancy', 5, 2)->default(70);
            $table->decimal('daily_rate', 15, 2)->nullable();
            $table->decimal('ota_commission', 5, 2)->default(15);
            $table->unsignedSmallInteger('projection_days')->default(30);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('business_simulation_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_simulation_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20)->index();
            $table->string('applies_to', 20)->index();
            $table->string('name', 120);
            $table->decimal('amount', 15, 2);
            $table->string('notes', 300)->nullable();
            $table->timestamps();
            $table->index(['business_simulation_id', 'type'], 'business_sim_cost_sim_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_simulation_costs');
        Schema::dropIfExists('business_simulations');
    }
};
