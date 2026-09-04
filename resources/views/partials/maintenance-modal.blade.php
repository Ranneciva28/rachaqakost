<dialog class="modal" id="maintenanceModal">
    <div class="modalhead"><div><h2>Buat tiket maintenance</h2><p>Bisa mencatat maintenance aktif maupun histori masa lalu.</p></div><button class="close" data-close>×</button></div>
    <form class="form" method="post" action="{{ route('maintenances.store') }}">@csrf
        <div class="field"><label>Kamar</label><select name="room_id" required>@foreach($rooms as $room)<option value="{{ $room->id }}">#{{ $room->number }} · {{ $room->category->name }}</option>@endforeach</select></div>
        <div class="field"><label>Masalah</label><input name="title" maxlength="150" required></div>
        <div class="formgrid">
            <div class="field"><label>Status</label><select name="status" id="maintenanceStatus"><option value="DIJADWALKAN">Dijadwalkan</option><option value="DIKERJAKAN">Dikerjakan</option><option value="SELESAI">Selesai (Historis)</option></select></div>
            <div class="field"><label id="maintenanceCostLabel">Estimasi biaya</label><div class="currency-input"><span>Rp</span><input type="text" name="cost" data-currency inputmode="numeric" value="0"></div></div>
            <div class="field"><label>Tanggal laporan/kejadian</label><input type="date" name="reported_at" id="maintenanceReportedAt" value="{{ today()->toDateString() }}" required></div>
            <div class="field" id="maintenanceCompletedField" hidden><label>Tanggal selesai/pengeluaran</label><input type="date" name="completed_at" id="maintenanceCompletedAt" value="{{ today()->toDateString() }}"><small class="field-help">Biaya otomatis masuk Pengeluaran pada tanggal ini.</small></div>
        </div>
        <div class="field"><label>Catatan</label><textarea name="notes" maxlength="500"></textarea></div>
        <div class="modalfoot"><button type="button" class="btn secondary" data-close>Batal</button><button class="btn" id="maintenanceSubmit">Buat tiket</button></div>
    </form>
</dialog>
