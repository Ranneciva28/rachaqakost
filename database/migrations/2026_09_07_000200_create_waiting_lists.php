<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waiting_list_fields', function (Blueprint $table) {
            $table->id();
            $table->string('key', 80)->unique();
            $table->string('label', 120);
            $table->string('type', 30);
            $table->string('placeholder', 180)->nullable();
            $table->string('help_text', 300)->nullable();
            $table->boolean('required')->default(false);
            $table->json('options')->nullable();
            $table->unsignedSmallInteger('position')->default(0)->index();
            $table->boolean('active')->default(true)->index();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        Schema::create('waiting_list_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category_name', 120);
            $table->string('name', 120);
            $table->string('phone', 30);
            $table->unsignedTinyInteger('preferred_floor');
            $table->date('followup_until');
            $table->json('responses')->nullable();
            $table->string('status', 30)->default('NEW')->index();
            $table->timestamp('submitted_at')->index();
            $table->timestamp('followed_up_at')->nullable();
            $table->foreignId('followed_up_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'submitted_at'], 'waiting_entries_status_submitted_idx');
            $table->index(['room_category_id', 'status'], 'waiting_entries_category_status_idx');
        });

        $now = now();
        DB::table('waiting_list_fields')->insert([
            ['key'=>'name','label'=>'Nama','type'=>'short_text','placeholder'=>'Nama lengkap','help_text'=>null,'required'=>true,'options'=>null,'position'=>1,'active'=>true,'is_system'=>true,'created_at'=>$now,'updated_at'=>$now],
            ['key'=>'phone','label'=>'No. HP / WhatsApp','type'=>'phone','placeholder'=>'Contoh: 081234567890','help_text'=>'Pastikan nomor aktif agar tim kami dapat menghubungi Anda.','required'=>true,'options'=>null,'position'=>2,'active'=>true,'is_system'=>true,'created_at'=>$now,'updated_at'=>$now],
            ['key'=>'room_category','label'=>'Kategori Kamar yang Diminati','type'=>'category','placeholder'=>null,'help_text'=>'Terisi otomatis berdasarkan kategori yang Anda pilih.','required'=>true,'options'=>null,'position'=>3,'active'=>true,'is_system'=>true,'created_at'=>$now,'updated_at'=>$now],
            ['key'=>'preferred_floor','label'=>'Preferensi Lantai','type'=>'select','placeholder'=>null,'help_text'=>'Pilih lantai yang paling diminati.','required'=>true,'options'=>json_encode(['1','2']),'position'=>4,'active'=>true,'is_system'=>true,'created_at'=>$now,'updated_at'=>$now],
            ['key'=>'followup_until','label'=>'Follow-up Saya Maksimal pada Bulan','type'=>'month','placeholder'=>null,'help_text'=>'Kami akan menghubungi Anda jika kamar tersedia sampai bulan tersebut.','required'=>true,'options'=>null,'position'=>5,'active'=>true,'is_system'=>true,'created_at'=>$now,'updated_at'=>$now],
        ]);

        DB::table('tenant_form_fields')->where('key','ktp_upload')->where('help_text','Format JPG, JPEG, PNG, WEBP, atau PDF. Maksimal 5 MB.')
            ->update(['help_text'=>'Format JPG, JPEG, PNG, WEBP, atau PDF. Maksimal 25 MB.']);
    }

    public function down(): void
    {
        DB::table('tenant_form_fields')->where('key','ktp_upload')->where('help_text','Format JPG, JPEG, PNG, WEBP, atau PDF. Maksimal 25 MB.')
            ->update(['help_text'=>'Format JPG, JPEG, PNG, WEBP, atau PDF. Maksimal 5 MB.']);
        Schema::dropIfExists('waiting_list_entries');
        Schema::dropIfExists('waiting_list_fields');
    }
};
