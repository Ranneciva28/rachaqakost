<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>RachaqaKost — Simulasi Bisnis</title>
    @include('partials.branding-head')
    <link rel="stylesheet" href="{{ asset('assets/rachaqakost.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/rachaqakost-fixes.css') }}?v=20261028">
    <link rel="stylesheet" href="{{ asset('assets/mobile-admin-navigation.css') }}?v=20260907">
    <link rel="stylesheet" href="{{ asset('assets/business-simulation.css') }}?v=20261101">
</head>
<body>
@php
$activeTab='business-simulation';
$scenarioLabel=['FULL_MONTHLY'=>'Full Bulanan','HYBRID'=>'Hybrid','BOTH'=>'Keduanya'];
$money=fn($value)=>'Rp '.number_format((float)$value,0,',','.');
$percent=fn($value)=>$value===null?'—':number_format((float)$value,1,',','.').'%';
$duration=fn($value)=>$value===null?'—':number_format((float)$value,1,',','.').' bulan';
@endphp
<div class="shell simulation-shell">
    @include('partials.admin-navigation')
    <main class="main simulation-main">
        <header class="top simulation-top">
            <div><span class="simulation-eyebrow">DECISION SUPPORT</span><h1>Simulasi Bisnis</h1><p>Bandingkan Full Bulanan dengan Hybrid berdasarkan struktur kamar aktual.</p></div>
            <span class="simulation-live"><i></i>Data kamar live</span>
        </header>

        @if(session('success'))<div class="toast" id="toast">{{ session('success') }}</div>@endif
        @if($errors->any())<div class="toast error" id="toast">{{ $errors->first() }}</div>@endif

        <section class="simulation-intro">
            <div><span>Basis perbandingan</span><h2>{{ $facts['total_rooms'] }} kamar aktual</h2><p>Lantai 1 diasumsikan menjadi kamar harian. Lantai 2 dan seterusnya tetap bulanan. Perubahan kategori, tarif, jumlah kamar, atau lantai otomatis terbaca saat halaman dibuka ulang.</p></div>
            <div class="simulation-room-split"><article><small>Full Bulanan</small><strong>{{ $facts['total_rooms'] }}</strong><span>kamar bulanan</span></article><b>VS</b><article><small>Hybrid</small><strong>{{ $facts['daily_rooms'] }} + {{ $facts['monthly_rooms'] }}</strong><span>harian + bulanan</span></article></div>
        </section>

        <section class="simulation-layout">
            <form class="panel simulation-assumptions" method="post" action="{{ route('business-simulation.update',$simulation) }}" data-simulation-form>
                @csrf @method('PATCH')
                <div class="panelhead"><div><h2>Asumsi simulasi</h2><p>Hasil di kanan berubah langsung sebelum disimpan.</p></div><span class="badge">Interaktif</span></div>
                <div class="simulation-form-body">
                    <label class="simulation-field"><span>Nama simulasi</span><input name="name" value="{{ old('name',$simulation->name) }}" maxlength="120" required></label>
                    <label class="simulation-field occupancy-field"><span>Okupansi kamar harian</span><div><input type="range" min="0" max="100" step="1" value="{{ old('daily_occupancy',$simulation->daily_occupancy) }}" data-occupancy-range><input type="number" name="daily_occupancy" min="0" max="100" step="0.01" value="{{ old('daily_occupancy',$simulation->daily_occupancy) }}" data-occupancy-number required><b>%</b></div></label>
                    <div class="simulation-form-grid">
                        <label class="simulation-field"><span>Tarif harian</span><div class="currency-input"><span>Rp</span><input type="text" name="daily_rate" data-currency data-simulation-daily-rate inputmode="numeric" value="{{ old('daily_rate',$simulation->daily_rate!==null?number_format((float)$simulation->daily_rate,0,',','.'):'') }}" placeholder="{{ number_format($facts['actual_daily_rate'],0,',','.') }}"></div><small>Kosongkan untuk memakai rata-rata tarif aktual lantai 1.</small></label>
                        <label class="simulation-field"><span>Komisi OTA / Traveloka</span><div class="input-suffix"><input type="number" name="ota_commission" min="0" max="100" step="0.01" value="{{ old('ota_commission',$simulation->ota_commission) }}" data-simulation-commission required><b>%</b></div></label>
                        <label class="simulation-field"><span>Jumlah hari proyeksi</span><div class="input-suffix"><input type="number" name="projection_days" min="1" max="366" value="{{ old('projection_days',$simulation->projection_days) }}" data-simulation-days required><b>hari</b></div></label>
                    </div>
                    <button class="btn simulation-save">Simpan asumsi</button>
                </div>
            </form>

            <section class="simulation-results" aria-live="polite">
                <article class="simulation-winner"><span>Selisih laba bersih / bulan</span><strong data-metric="difference">{{ $money(abs($metrics['difference'])) }}</strong><p data-difference-label>{{ $metrics['difference']>=0?'Hybrid lebih menguntungkan':'Full Bulanan lebih menguntungkan' }}</p></article>
                <div class="scenario-grid">
                    <article class="scenario-card full"><header><span>SKENARIO A</span><h2>Full Bulanan</h2><small>{{ $facts['total_rooms'] }} kamar menggunakan tarif bulanan aktual</small></header><dl><div><dt>Omzet kotor</dt><dd data-metric="full.gross_revenue">{{ $money($metrics['full']['gross_revenue']) }}</dd></div><div><dt>Biaya operasional</dt><dd data-metric="full.operating_cost">{{ $money($metrics['full']['operating_cost']) }}</dd></div><div class="profit"><dt>Laba bersih</dt><dd data-metric="full.net_profit">{{ $money($metrics['full']['net_profit']) }}</dd></div><div><dt>Break-even occupancy</dt><dd data-metric="full_break_even_occupancy">{{ $percent($metrics['full_break_even_occupancy']) }}</dd></div><div><dt>ROI tahunan</dt><dd data-metric="full.roi">{{ $percent($metrics['full']['roi']) }}</dd></div><div><dt>Payback period</dt><dd data-metric="full.payback_months">{{ $duration($metrics['full']['payback_months']) }}</dd></div></dl></article>
                    <article class="scenario-card hybrid"><header><span>SKENARIO B</span><h2>Hybrid</h2><small>{{ $facts['daily_rooms'] }} kamar harian lantai 1 + {{ $facts['monthly_rooms'] }} kamar bulanan lantai atas</small></header><dl><div><dt>Omzet kotor</dt><dd data-metric="hybrid.gross_revenue">{{ $money($metrics['hybrid']['gross_revenue']) }}</dd></div><div><dt>Komisi OTA</dt><dd data-metric="hybrid.ota_fee">{{ $money($metrics['hybrid']['ota_fee']) }}</dd></div><div><dt>Biaya operasional</dt><dd data-metric="hybrid.operating_cost">{{ $money($metrics['hybrid']['operating_cost']) }}</dd></div><div class="profit"><dt>Laba bersih</dt><dd data-metric="hybrid.net_profit">{{ $money($metrics['hybrid']['net_profit']) }}</dd></div><div><dt>Break-even occupancy</dt><dd data-metric="hybrid_break_even_occupancy">{{ $percent($metrics['hybrid_break_even_occupancy']) }}</dd></div><div><dt>ROI tahunan</dt><dd data-metric="hybrid.roi">{{ $percent($metrics['hybrid']['roi']) }}</dd></div><div><dt>Payback period</dt><dd data-metric="hybrid.payback_months">{{ $duration($metrics['hybrid']['payback_months']) }}</dd></div></dl></article>
                </div>
                <aside class="parity-note"><span>Okupansi Hybrid untuk menyamai laba Full Bulanan</span><strong data-metric="hybrid_parity_occupancy">{{ $percent($metrics['hybrid_parity_occupancy']) }}</strong><small>Angka di atas 100% berarti asumsi tarif atau struktur biaya Hybrid belum dapat melampaui Full Bulanan.</small></aside>
            </section>
        </section>

        <section class="panel simulation-source">
            <div class="panelhead"><div><h2>Data aktual yang dipakai</h2><p>Sumber langsung dari kategori, tarif, jumlah kamar, dan posisi lantai di database.</p></div><span class="badge gray">Read-only</span></div>
            <div class="tablewrap"><table class="table"><thead><tr><th>Lantai</th><th>Kategori</th><th>Jumlah kamar</th><th>Tarif harian</th><th>Tarif bulanan</th><th>Peran Hybrid</th></tr></thead><tbody>@forelse($facts['rows'] as $row)<tr><td><strong>Lantai {{ $row['floor'] }}</strong></td><td>{{ $row['category'] }}</td><td>{{ $row['rooms'] }} kamar</td><td>{{ $money($row['daily_rate']) }}</td><td>{{ $money($row['monthly_rate']) }}</td><td><span class="badge {{ $row['floor']===1?'orange':'' }}">{{ $row['floor']===1?'Harian':'Bulanan' }}</span></td></tr>@empty<tr><td colspan="6" class="empty">Belum ada data kamar. Tambahkan kamar terlebih dahulu.</td></tr>@endforelse</tbody></table></div>
        </section>

        <section class="simulation-costs">
            <div class="sectionhead"><div><h2>Biaya & investasi</h2><p>Biaya operasional dihitung per bulan. Investasi dipakai untuk menghitung ROI dan payback period.</p></div><button type="button" class="btn" data-open="simulationCostModal">+ Tambah komponen</button></div>
            <div class="cost-summary-grid">
                @foreach(['OPERATIONAL'=>'Biaya operasional / bulan','INVESTMENT'=>'Investasi renovasi'] as $type=>$label)
                <article class="panel cost-panel"><div class="panelhead"><div><h2>{{ $label }}</h2><p>{{ $type==='OPERATIONAL'?'Mengurangi laba bersih bulanan':'Tidak mengurangi laba bulanan secara langsung' }}</p></div><strong>{{ $money($simulation->costs->where('type',$type)->sum('amount')) }}</strong></div><div class="cost-list">
                    @forelse($simulation->costs->where('type',$type) as $cost)
                    <details class="cost-item"><summary><span><b>{{ $cost->name }}</b><small>{{ $scenarioLabel[$cost->applies_to] }}{{ $cost->notes?' · '.$cost->notes:'' }}</small></span><strong>{{ $money($cost->amount) }}</strong></summary><form method="post" action="{{ route('business-simulation.costs.update',$cost) }}" class="cost-edit-form">@csrf @method('PATCH')<div class="simulation-form-grid"><label class="simulation-field"><span>Jenis</span><select name="type"><option value="OPERATIONAL" @selected($cost->type==='OPERATIONAL')>Biaya operasional</option><option value="INVESTMENT" @selected($cost->type==='INVESTMENT')>Investasi renovasi</option></select></label><label class="simulation-field"><span>Berlaku untuk</span><select name="applies_to">@foreach($scenarioLabel as $key=>$value)<option value="{{ $key }}" @selected($cost->applies_to===$key)>{{ $value }}</option>@endforeach</select></label><label class="simulation-field"><span>Nama</span><input name="name" value="{{ $cost->name }}" maxlength="120" required></label><label class="simulation-field"><span>Nominal</span><div class="currency-input"><span>Rp</span><input name="amount" value="{{ number_format((float)$cost->amount,0,',','.') }}" data-currency inputmode="numeric" required></div></label></div><label class="simulation-field"><span>Catatan</span><input name="notes" value="{{ $cost->notes }}" maxlength="300"></label><div class="cost-actions"><button class="btn small">Simpan perubahan</button></div></form><form class="cost-delete-form" method="post" action="{{ route('business-simulation.costs.destroy',$cost) }}" onsubmit="return confirm('Hapus komponen biaya ini?')">@csrf @method('DELETE')<button class="btn danger small">Hapus komponen</button></form></details>
                    @empty<div class="empty">Belum ada komponen {{ strtolower($label) }}.</div>@endforelse
                </div></article>
                @endforeach
            </div>
        </section>

        <aside class="simulation-disclaimer"><b>Catatan</b><p>Simulasi ini adalah alat pengambilan keputusan, bukan pencatatan transaksi aktual. Omzet Hybrid dikurangi komisi OTA sebelum biaya operasional. Pajak, inflasi, biaya tak terduga, dan perubahan tarif hanya ikut dihitung jika ditambahkan sebagai komponen biaya atau asumsi.</p></aside>
    </main>
</div>

<dialog class="modal" id="simulationCostModal"><div class="modalhead"><div><h2>Tambah komponen</h2><p>Masukkan biaya bulanan atau investasi renovasi.</p></div><button type="button" class="close" data-close>×</button></div><form class="form" method="post" action="{{ route('business-simulation.costs.store',$simulation) }}">@csrf<div class="formgrid"><div class="field"><label>Jenis komponen</label><select name="type" required><option value="OPERATIONAL">Biaya operasional / bulan</option><option value="INVESTMENT">Investasi renovasi</option></select></div><div class="field"><label>Berlaku untuk</label><select name="applies_to" required><option value="HYBRID">Hybrid</option><option value="FULL_MONTHLY">Full Bulanan</option><option value="BOTH">Keduanya</option></select></div></div><div class="field"><label>Nama komponen</label><input name="name" maxlength="120" placeholder="Contoh: Laundry linen" required></div><div class="field"><label>Nominal</label><div class="currency-input"><span>Rp</span><input name="amount" data-currency inputmode="numeric" placeholder="0" required></div></div><div class="field"><label>Catatan (opsional)</label><input name="notes" maxlength="300" placeholder="Keterangan singkat"></div><div class="modalfoot"><button type="button" class="btn secondary" data-close>Batal</button><button class="btn">Tambahkan</button></div></form></dialog>

<script type="application/json" id="simulationFacts">{!! json_encode($facts, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!}</script>
<script type="application/json" id="simulationCosts">{!! $simulation->costs->map->only(['type','applies_to','amount'])->values()->toJson(JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!}</script>
<script src="{{ asset('assets/rachaqakost.js') }}?v=20261028-payment-submit-guard"></script>
<script src="{{ asset('assets/business-simulation.js') }}?v=20261101"></script>
</body>
</html>
