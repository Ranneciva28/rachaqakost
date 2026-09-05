<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_form_sections', function (Blueprint $table) {
            $table->id();
            $table->string('title', 120);
            $table->string('description', 300)->nullable();
            $table->unsignedSmallInteger('position')->default(0)->index();
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('tenant_form_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_form_section_id')->constrained()->cascadeOnDelete();
            $table->string('key', 80)->unique();
            $table->string('label', 120);
            $table->string('type', 30);
            $table->string('placeholder', 180)->nullable();
            $table->string('help_text', 300)->nullable();
            $table->boolean('required')->default(false);
            $table->json('options')->nullable();
            $table->unsignedSmallInteger('position')->default(0)->index();
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
            $table->index(['tenant_form_section_id', 'active', 'position'], 'tenant_form_fields_section_active_position_idx');
        });

        Schema::table('tenant_data_forms', function (Blueprint $table) {
            $table->json('responses')->nullable();
        });

        Schema::create('media_files', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 30)->index();
            $table->foreignId('room_category_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_data_form_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_form_field_id')->nullable()->constrained()->nullOnDelete();
            $table->string('original_name', 255);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->binary('contents');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
            $table->index(['room_category_id', 'kind', 'position'], 'media_files_category_kind_position_idx');
            $table->index(['tenant_data_form_id', 'kind'], 'media_files_form_kind_idx');
        });

        $now = now();
        $sections = [
            ['title'=>'Identitas Penghuni','description'=>'Data identitas resmi dan kontak utama.','position'=>1,'active'=>true,'created_at'=>$now,'updated_at'=>$now],
            ['title'=>'Pekerjaan & Alamat','description'=>'Informasi aktivitas dan domisili penghuni.','position'=>2,'active'=>true,'created_at'=>$now,'updated_at'=>$now],
            ['title'=>'Kontak Darurat & Kendaraan','description'=>'Kontak keluarga serta data kendaraan yang dibawa.','position'=>3,'active'=>true,'created_at'=>$now,'updated_at'=>$now],
            ['title'=>'Dokumen Pendukung','description'=>'Dokumen identitas untuk verifikasi pengelola.','position'=>4,'active'=>true,'created_at'=>$now,'updated_at'=>$now],
        ];
        DB::table('tenant_form_sections')->insert($sections);
        $ids = DB::table('tenant_form_sections')->pluck('id', 'title');
        $fields = [
            ['Identitas Penghuni','full_name','Nama lengkap','short_text',true,null,null,1],
            ['Identitas Penghuni','phone','Nomor HP / WhatsApp','phone',true,null,null,2],
            ['Identitas Penghuni','identity_number','NIK','number',true,null,'Isi 16 digit NIK.',3],
            ['Identitas Penghuni','email','Email','email',false,null,null,4],
            ['Identitas Penghuni','birth_place','Tempat lahir','short_text',true,null,null,5],
            ['Identitas Penghuni','birth_date','Tanggal lahir','date',true,null,null,6],
            ['Identitas Penghuni','gender','Jenis kelamin','select',true,['Laki-laki','Perempuan'],null,7],
            ['Pekerjaan & Alamat','occupation','Pekerjaan / status','short_text',true,null,'Contoh: Karyawan, mahasiswa.',1],
            ['Pekerjaan & Alamat','employer_or_school','Perusahaan / sekolah','short_text',false,null,null,2],
            ['Pekerjaan & Alamat','identity_address','Alamat sesuai identitas','long_text',true,null,null,3],
            ['Pekerjaan & Alamat','domicile_address','Alamat domisili / asal sebelum kost','long_text',true,null,null,4],
            ['Kontak Darurat & Kendaraan','emergency_name','Nama kontak darurat','short_text',true,null,null,1],
            ['Kontak Darurat & Kendaraan','emergency_relationship','Hubungan kontak darurat','short_text',true,null,null,2],
            ['Kontak Darurat & Kendaraan','emergency_phone','Nomor kontak darurat','phone',true,null,null,3],
            ['Kontak Darurat & Kendaraan','vehicle_type','Jenis kendaraan','short_text',false,null,'Kosongkan jika tidak ada.',4],
            ['Kontak Darurat & Kendaraan','vehicle_plate','Nomor polisi','short_text',false,null,null,5],
            ['Kontak Darurat & Kendaraan','additional_notes','Catatan tambahan','long_text',false,null,null,6],
            ['Dokumen Pendukung','ktp_upload','Upload KTP','file',true,null,'Format JPG, JPEG, PNG, WEBP, atau PDF. Maksimal 5 MB.',1],
        ];
        foreach ($fields as [$section,$key,$label,$type,$required,$options,$help,$position]) {
            DB::table('tenant_form_fields')->insert([
                'tenant_form_section_id'=>$ids[$section], 'key'=>$key, 'label'=>$label, 'type'=>$type,
                'required'=>$required, 'options'=>$options ? json_encode($options) : null,
                'help_text'=>$help, 'position'=>$position, 'active'=>true, 'created_at'=>$now, 'updated_at'=>$now,
            ]);
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE tenant_data_forms ALTER COLUMN full_name DROP NOT NULL');
            DB::statement('ALTER TABLE tenant_data_forms ALTER COLUMN phone DROP NOT NULL');
            DB::statement('ALTER TABLE tenant_data_forms ALTER COLUMN identity_number DROP NOT NULL');
            DB::statement('ALTER TABLE tenant_data_forms ALTER COLUMN birth_place DROP NOT NULL');
            DB::statement('ALTER TABLE tenant_data_forms ALTER COLUMN birth_date DROP NOT NULL');
            DB::statement('ALTER TABLE tenant_data_forms ALTER COLUMN gender DROP NOT NULL');
            DB::statement('ALTER TABLE tenant_data_forms ALTER COLUMN occupation DROP NOT NULL');
            DB::statement('ALTER TABLE tenant_data_forms ALTER COLUMN identity_address DROP NOT NULL');
            DB::statement('ALTER TABLE tenant_data_forms ALTER COLUMN domicile_address DROP NOT NULL');
            DB::statement('ALTER TABLE tenant_data_forms ALTER COLUMN emergency_name DROP NOT NULL');
            DB::statement('ALTER TABLE tenant_data_forms ALTER COLUMN emergency_relationship DROP NOT NULL');
            DB::statement('ALTER TABLE tenant_data_forms ALTER COLUMN emergency_phone DROP NOT NULL');
            DB::statement("UPDATE tenant_data_forms SET responses = jsonb_build_object('full_name',full_name,'phone',phone,'identity_number',identity_number,'email',email,'birth_place',birth_place,'birth_date',birth_date,'gender',gender,'occupation',occupation,'employer_or_school',employer_or_school,'identity_address',identity_address,'domicile_address',domicile_address,'emergency_name',emergency_name,'emergency_relationship',emergency_relationship,'emergency_phone',emergency_phone,'vehicle_type',vehicle_type,'vehicle_plate',vehicle_plate,'additional_notes',additional_notes)::json WHERE responses IS NULL");
            foreach (['tenant_form_sections','tenant_form_fields','media_files'] as $table) {
                DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
                DB::statement("REVOKE ALL ON TABLE {$table} FROM anon, authenticated");
                DB::statement("REVOKE ALL ON SEQUENCE {$table}_id_seq FROM anon, authenticated");
            }
            DB::statement("ALTER TABLE tenant_form_fields ADD CONSTRAINT tenant_form_fields_type_valid CHECK (type IN ('short_text','long_text','number','date','email','phone','select','file'))");
            DB::statement("ALTER TABLE media_files ADD CONSTRAINT media_files_kind_valid CHECK (kind IN ('KTP','CATEGORY','HERO'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('media_files');
        Schema::table('tenant_data_forms', fn (Blueprint $table) => $table->dropColumn('responses'));
        Schema::dropIfExists('tenant_form_fields');
        Schema::dropIfExists('tenant_form_sections');
    }
};
