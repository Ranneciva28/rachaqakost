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
            $table->string('form_token', 80)->nullable()->unique();
        });

        DB::table('tenants')->select(['id', 'phone', 'identity_number'])->orderBy('id')->each(function ($tenant) {
            $phone = preg_replace('/\D+/', '', (string) $tenant->phone);
            $identity = preg_replace('/\D+/', '', (string) $tenant->identity_number);
            $phonePart = str_pad(substr($phone, -4), 4, '0', STR_PAD_LEFT);
            $identityPart = str_pad(substr($identity, -3), 3, '0', STR_PAD_LEFT);
            $random = rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
            DB::table('tenants')->where('id', $tenant->id)->update(['form_token' => $phonePart.$identityPart.'-'.$random]);
        });

        Schema::create('tenant_data_forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status', 30)->default('PENDING_APPROVAL')->index();
            $table->string('full_name', 120)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('identity_number', 40)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('birth_place', 100)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('gender', 20)->nullable();
            $table->string('occupation', 120)->nullable();
            $table->string('employer_or_school', 150)->nullable();
            $table->text('identity_address')->nullable();
            $table->text('domicile_address')->nullable();
            $table->string('emergency_name', 120)->nullable();
            $table->string('emergency_relationship', 60)->nullable();
            $table->string('emergency_phone', 30)->nullable();
            $table->string('vehicle_type', 60)->nullable();
            $table->string('vehicle_plate', 20)->nullable();
            $table->text('additional_notes')->nullable();
            $table->timestamp('submitted_at');
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('revision_opened_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'submitted_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE tenants ALTER COLUMN form_token SET NOT NULL');
            DB::statement("ALTER TABLE tenant_data_forms ADD CONSTRAINT tenant_data_forms_status_valid CHECK (status IN ('PENDING_APPROVAL', 'VALID', 'REVISION'))");
            DB::statement('ALTER TABLE tenant_data_forms ENABLE ROW LEVEL SECURITY');
            DB::statement('REVOKE ALL ON TABLE tenant_data_forms FROM anon, authenticated');
            DB::statement('REVOKE ALL ON SEQUENCE tenant_data_forms_id_seq FROM anon, authenticated');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_data_forms');
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropUnique(['form_token']);
            $table->dropColumn('form_token');
        });
    }
};
