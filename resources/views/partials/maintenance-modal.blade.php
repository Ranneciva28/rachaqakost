<dialog class="modal" id="maintenanceModal">
    <div class="modalhead"><div><h2>Catat maintenance</h2><p>Langsung masuk riwayat kamar dan pengeluaran bulanan.</p></div><button class="close" data-close>×</button></div>
    <form class="form" method="post" action="{{ route('maintenances.store') }}">@csrf
        <div class="field"><label>Kamar</label><select name="room_id" required>@foreach($rooms as $room)<option value="{{ $room->id }}">#{{ $room->number }} · {{ $room->category->name }}</option>@endforeach</select></div>
        <div class="field"><label>Masalah</label><input name="title" maxlength="150" required></div>
        <div class="formgrid">
            <div class="field"><label>Biaya</label><div class="currency-input"><span>Rp</span><input type="text" name="cost" data-currency inputmode="numeric" value="0" required></div></div>
            <div class="field"><label>Tanggal maintenance</label><input type="date" name="maintenance_at" value="{{ today()->toDateString() }}" required><small class="field-help">Tanggal ini juga menentukan bulan pencatatan di laporan keuangan.</small></div>
        </div>
        <div class="field"><label>Catatan</label><textarea name="notes" maxlength="500"></textarea></div>
        <div class="modalfoot"><button type="button" class="btn secondary" data-close>Batal</button><button class="btn">Simpan maintenance</button></div>
    </form>
</dialog>
