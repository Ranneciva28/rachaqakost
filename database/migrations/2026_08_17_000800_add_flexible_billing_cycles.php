<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_categories', function (Blueprint $table) {
            $table->decimal('daily_price', 15, 2)->default(0);
            $table->decimal('weekly_price', 15, 2)->default(0);
        });
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('billing_cycle', 12)->default('MONTHLY')->index();
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->string('billing_cycle', 12)->default('MONTHLY')->index();
            $table->unsignedSmallInteger('period_count')->default(1);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE room_categories ADD CONSTRAINT room_categories_daily_price_nonnegative CHECK (daily_price >= 0)');
            DB::statement('ALTER TABLE room_categories ADD CONSTRAINT room_categories_weekly_price_nonnegative CHECK (weekly_price >= 0)');
            DB::statement("ALTER TABLE tenants ADD CONSTRAINT tenants_billing_cycle_valid CHECK (billing_cycle IN ('DAILY','WEEKLY','MONTHLY'))");
            DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_billing_cycle_valid CHECK (billing_cycle IN ('DAILY','WEEKLY','MONTHLY'))");
            DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_period_count_positive CHECK (period_count > 0)');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE room_categories DROP CONSTRAINT IF EXISTS room_categories_daily_price_nonnegative');
            DB::statement('ALTER TABLE room_categories DROP CONSTRAINT IF EXISTS room_categories_weekly_price_nonnegative');
            DB::statement('ALTER TABLE tenants DROP CONSTRAINT IF EXISTS tenants_billing_cycle_valid');
            DB::statement('ALTER TABLE payments DROP CONSTRAINT IF EXISTS payments_billing_cycle_valid');
            DB::statement('ALTER TABLE payments DROP CONSTRAINT IF EXISTS payments_period_count_positive');
        }
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['billing_cycle']);
            $table->dropColumn(['billing_cycle', 'period_count']);
        });
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropIndex(['billing_cycle']);
            $table->dropColumn('billing_cycle');
        });
        Schema::table('room_categories', function (Blueprint $table) {
            $table->dropColumn(['daily_price', 'weekly_price']);
        });
    }
};
