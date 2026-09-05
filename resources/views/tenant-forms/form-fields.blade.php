@php
    $value = fn(string $key, $fallback = '') => old($key, $form?->{$key} ?? $fallback);
@endphp
<section class="form-section">
    <div class="form-section-title"><span>01</span><div><h2>Identitas penghuni</h2><p>Isi sesuai identitas resmi yang berlaku.</p></div></div>
    <div class="public-form-grid">
        <label class="public-field wide"><span>Nama lengkap</span><input name="full_name" value="{{ $value('full_name', $tenant->name) }}" maxlength="120" required @readonly(!$editable)></label>
        <label class="public-field"><span>Nomor HP / WhatsApp</span><input name="phone" value="{{ $value('phone', $tenant->phone) }}" maxlength="30" required @readonly(!$editable)></label>
        <label class="public-field"><span>NIK</span><input name="identity_number" value="{{ $value('identity_number', $tenant->identity_number) }}" inputmode="numeric" maxlength="20" required @readonly(!$editable)></label>
        <label class="public-field"><span>Email</span><input type="email" name="email" value="{{ $value('email') }}" maxlength="150" @readonly(!$editable)></label>
        <label class="public-field"><span>Tempat lahir</span><input name="birth_place" value="{{ $value('birth_place') }}" maxlength="100" required @readonly(!$editable)></label>
        <label class="public-field"><span>Tanggal lahir</span><input type="date" name="birth_date" value="{{ old('birth_date', $form?->birth_date?->toDateString()) }}" max="{{ today()->subDay()->toDateString() }}" required @readonly(!$editable)></label>
        <label class="public-field"><span>Jenis kelamin</span><select name="gender" required @disabled(!$editable)><option value="">Pilih</option><option value="Laki-laki" @selected($value('gender')==='Laki-laki')>Laki-laki</option><option value="Perempuan" @selected($value('gender')==='Perempuan')>Perempuan</option></select></label>
    </div>
</section>

<section class="form-section">
    <div class="form-section-title"><span>02</span><div><h2>Pekerjaan & alamat</h2><p>Informasi aktivitas dan domisili saat ini.</p></div></div>
    <div class="public-form-grid">
        <label class="public-field"><span>Pekerjaan / status</span><input name="occupation" value="{{ $value('occupation') }}" maxlength="120" placeholder="Contoh: Karyawan, mahasiswa" required @readonly(!$editable)></label>
        <label class="public-field"><span>Perusahaan / sekolah</span><input name="employer_or_school" value="{{ $value('employer_or_school') }}" maxlength="150" @readonly(!$editable)></label>
        <label class="public-field wide"><span>Alamat sesuai identitas</span><textarea name="identity_address" maxlength="1000" required @readonly(!$editable)>{{ $value('identity_address') }}</textarea></label>
        <label class="public-field wide"><span>Alamat domisili / asal sebelum tinggal di kost</span><textarea name="domicile_address" maxlength="1000" required @readonly(!$editable)>{{ $value('domicile_address') }}</textarea></label>
    </div>
</section>

<section class="form-section">
    <div class="form-section-title"><span>03</span><div><h2>Kontak darurat & kendaraan</h2><p>Digunakan hanya jika pengelola perlu menghubungi keluarga atau penanggung jawab.</p></div></div>
    <div class="public-form-grid">
        <label class="public-field"><span>Nama kontak darurat</span><input name="emergency_name" value="{{ $value('emergency_name') }}" maxlength="120" required @readonly(!$editable)></label>
        <label class="public-field"><span>Hubungan</span><input name="emergency_relationship" value="{{ $value('emergency_relationship') }}" maxlength="60" placeholder="Contoh: Orang tua, saudara" required @readonly(!$editable)></label>
        <label class="public-field"><span>Nomor kontak darurat</span><input name="emergency_phone" value="{{ $value('emergency_phone') }}" maxlength="30" required @readonly(!$editable)></label>
        <label class="public-field"><span>Jenis kendaraan</span><input name="vehicle_type" value="{{ $value('vehicle_type') }}" maxlength="60" placeholder="Kosongkan jika tidak ada" @readonly(!$editable)></label>
        <label class="public-field"><span>Nomor polisi</span><input name="vehicle_plate" value="{{ $value('vehicle_plate') }}" maxlength="20" @readonly(!$editable)></label>
        <label class="public-field wide"><span>Catatan tambahan</span><textarea name="additional_notes" maxlength="1000" @readonly(!$editable)>{{ $value('additional_notes') }}</textarea></label>
    </div>
</section>
