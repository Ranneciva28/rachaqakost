<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>RachaqaKost — Laporan Keuangan</title>
    <link rel="stylesheet" href="{{ asset('assets/rachaqakost.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/rachaqakost-fixes.css') }}?v=20260905-finance-detail">
    <link rel="stylesheet" href="{{ asset('assets/finance-payment-detail.css') }}?v=20260905">
</head>
<body>
@php
$tabs=['dashboard'=>['⌂','Ringkasan'],'rooms'=>['▦','Kamar'],'tenants'=>['◎','Penghuni'],'payments'=>['↗','Pembayaran'],'expenses'=>['↘','Pengeluaran'],'maintenance'=>['◇','Maintenance'],'users'=>['♙','Tim']];
$periodLabel=$from->translatedFormat('d M Y').' – '.$to->translatedFormat('d M Y');
$comparisonText=function($value,$inverse=false){
    if($value===null)return 'Belum ada pembanding';
    if($value==0)return 'Sama dengan periode lalu';
    $better=$inverse?$value<0:$value>0;
    return ($value>0?'Naik ':'Turun ').number_format(abs($value),1,',','.').'% · '.($better?'positif':'perlu diperhatikan');
};
@endphp
<div class="shell finance-shell">
    <aside class="sidebar">
        <div class="brand"><span class="brandmark">K</span><div><b>RachaqaKost</b><small>Operational OS</small></div></div>
        <nav class="nav">
            @foreach($tabs as $key=>$tab)
                <a href="{{ route('dashboard',['tab'=>$key]) }}"><span class="ico">{{ $tab[0] }}</span>{{ $tab[1] }}</a>
            @endforeach
            <div class="nav-separator"></div>
            <a href="{{ route('finance') }}" class="active"><span class="ico">Rp</span>Keuangan</a>
            <a href="{{ route('imports.index') }}"><span class="ico">⇧</span>Import Data</a>
        </nav>
        <div class="profile"><b>{{ auth()->user()->name }}</b><small>Owner / Admin</small><form method="post" action="{{ route('logout') }}">@csrf<button class="logout">Keluar dari workspace</button></form></div>
    </aside>

    <main class="main finance-main">
        <header class="top finance-top">
            <div><span class="eyebrow">FINANCIAL COMMAND CENTER</span><h1>Laporan keuangan</h1><p>{{ $periodLabel }} · Laporan berbasis kas dari pembayaran dan pengeluaran tercatat</p></div>
            <div class="actions"><a class="btn secondary" href="{{ route('dashboard',['tab'=>'expenses']) }}">+ Pengeluaran</a><a class="btn" href="{{ route('dashboard',['tab'=>'payments']) }}">+ Pendapatan</a></div>
        </header>

        <section class="period-panel finance-period-panel">
            <form class="period-filter" method="get" action="{{ route('finance') }}">
                <div class="period-filter__intro"><span class="period-filter__icon">▣</span><div><b>Periode laporan</b><small>Seluruh kartu, grafik, kategori, dan rasio mengikuti rentang ini.</small></div></div>
                <div class="period-filter__controls">
                    <label class="period-filter__field"><span>Dari tanggal</span><input type="date" name="from" value="{{ $from->toDateString() }}" required></label>
                    <span class="period-filter__arrow">→</span>
                    <label class="period-filter__field"><span>Sampai tanggal</span><input type="date" name="to" value="{{ $to->toDateString() }}" required></label>
                    <button class="btn period-filter__apply">Terapkan</button>
                </div>
            </form>
            <nav class="period-presets" aria-label="Pilihan periode cepat">
                <span>Filter cepat</span>
                <a href="{{ route('finance') }}">Bulan ini</a>
                <a href="{{ route('finance',['from'=>now()->subMonthNoOverflow()->startOfMonth()->toDateString(),'to'=>now()->subMonthNoOverflow()->endOfMonth()->toDateString()]) }}">Bulan lalu</a>
                <a href="{{ route('finance',['from'=>now()->startOfYear()->toDateString(),'to'=>now()->toDateString()]) }}">Tahun berjalan</a>
                <a href="{{ route('finance',['from'=>now()->subYear()->startOfYear()->toDateString(),'to'=>now()->subYear()->endOfYear()->toDateString()]) }}">Tahun lalu</a>
            </nav>
        </section>

        <section class="finance-kpis">
            <article class="finance-kpi income"><div class="finance-kpi-head"><span>Pendapatan</span><i>↗</i></div><strong>Rp {{ number_format($income,0,',','.') }}</strong><small>{{ $incomeTransactions }} transaksi</small><em class="{{ ($comparison['income']??0)<0?'bad':'' }}">{{ $comparisonText($comparison['income']) }}</em></article>
            <article class="finance-kpi expense"><div class="finance-kpi-head"><span>Pengeluaran</span><i>↘</i></div><strong>Rp {{ number_format($expenseTotal,0,',','.') }}</strong><small>{{ $expenseTransactions }} transaksi</small><em class="{{ ($comparison['expense']??0)>0?'bad':'' }}">{{ $comparisonText($comparison['expense'],true) }}</em></article>
            <article class="finance-kpi gross"><div class="finance-kpi-head"><span>Gross profit</span><i>G</i></div><strong class="{{ $grossProfit<0?'negative':'' }}">Rp {{ number_format($grossProfit,0,',','.') }}</strong><small>Setelah biaya langsung Rp {{ number_format($directExpense,0,',','.') }}</small><em>{{ $income>0?number_format($grossProfit/$income*100,1,',','.').' % gross margin':'Belum dapat dihitung' }}</em></article>
            <article class="finance-kpi net"><div class="finance-kpi-head"><span>Net profit</span><i>N</i></div><strong class="{{ $netProfit<0?'negative':'' }}">Rp {{ number_format($netProfit,0,',','.') }}</strong><small>Setelah seluruh pengeluaran</small><em class="{{ ($comparison['profit']??0)<0?'bad':'' }}">{{ $comparisonText($comparison['profit']) }}</em></article>
        </section>

        <section class="finance-grid-main">
            <article class="panel pnl-card">
                <div class="panelhead"><div><h2>Ringkasan laba rugi</h2><p>Struktur sederhana berbasis kas untuk periode terpilih</p></div><span class="badge {{ $netProfit>=0?'':'red' }}">{{ $netProfit>=0?'Surplus':'Defisit' }}</span></div>
                <div class="pnl-list">
                    <div><span>Pendapatan sewa</span><strong>Rp {{ number_format($income,0,',','.') }}</strong></div>
                    <div class="deduction"><span>Biaya langsung</span><strong>− Rp {{ number_format($directExpense,0,',','.') }}</strong></div>
                    <div class="subtotal"><span>Gross profit</span><strong>Rp {{ number_format($grossProfit,0,',','.') }}</strong></div>
                    <div class="deduction"><span>Biaya operasional</span><strong>− Rp {{ number_format($operatingExpense,0,',','.') }}</strong></div>
                    <div class="total"><span>Net profit</span><strong class="{{ $netProfit<0?'negative':'' }}">Rp {{ number_format($netProfit,0,',','.') }}</strong></div>
                </div>
            </article>

            <article class="panel category-summary">
                <div class="panelhead"><div><h2>Kategori pengeluaran terbesar</h2><p>Kontributor utama terhadap total biaya</p></div></div>
                @if($largestCategory)
                    <div class="largest-category" style="--accent:{{ $largestCategory['color'] }}"><span class="largest-dot"></span><div><small>{{ $largestCategory['cost_type']==='DIRECT'?'Biaya langsung':'Biaya operasional' }} · {{ $largestCategory['cost_behavior']==='VARIABLE'?'Variable cost':'Fixed cost' }}</small><h3>{{ $largestCategory['name'] }}</h3><strong>Rp {{ number_format($largestCategory['amount'],0,',','.') }}</strong></div><b>{{ number_format($largestCategory['percentage'],1,',','.') }}%</b></div>
                    <div class="category-bars">
                        @foreach($categories as $category)
                            <div class="category-row"><div><span>{{ $category['name'] }} <em class="cost-behavior-pill {{ $category['cost_behavior']==='VARIABLE'?'variable':'fixed' }}">{{ $category['cost_behavior']==='VARIABLE'?'Variabel':'Tetap' }}</em></span><small>{{ $category['transactions'] }} transaksi · Rp {{ number_format($category['amount'],0,',','.') }}</small></div><b>{{ number_format($category['percentage'],1,',','.') }}%</b><div class="category-track"><i style="width:{{ $category['percentage'] }}%;background:{{ $category['color'] }}"></i></div></div>
                        @endforeach
                    </div>
                @else
                    <div class="empty">Belum ada pengeluaran pada periode ini.</div>
                @endif
            </article>
        </section>

        <section class="panel finance-trend-panel">
            <div class="panelhead"><div><h2>Tren pendapatan & pengeluaran</h2><p>Klik bar untuk melihat nominal tepatnya</p></div><span class="badge">{{ $trend->count() }} titik data</span></div>
            <div class="panelbody finance-chart-wrap">
                <div class="chart finance-chart">
                    @foreach($trend as $point)
                        <div class="chartcol"><div class="bars"><button type="button" class="bar" data-cashflow-bar data-tooltip="Pendapatan {{ $point['label'] }} · Rp {{ number_format($point['income'],0,',','.') }}" style="height:{{ max(2,round($point['income']/$maxTrend*100)) }}%"></button><button type="button" class="bar exp" data-cashflow-bar data-tooltip="Pengeluaran {{ $point['label'] }} · Rp {{ number_format($point['expense'],0,',','.') }}" style="height:{{ max(2,round($point['expense']/$maxTrend*100)) }}%"></button></div><small>{{ $point['label'] }}</small></div>
                    @endforeach
                </div>
                <div class="cashflow-value" id="cashflowValue" hidden>Pilih salah satu bar.</div>
                <div class="legend"><span><i class="dot"></i>Pendapatan</span><span><i class="dot orange"></i>Pengeluaran</span></div>
            </div>
        </section>

        <section class="finance-payment-section" id="detail-pembayaran">
            <div class="sectionhead finance-payment-heading">
                <div><h2>Detail pembayaran masuk</h2><p>Nomor kamar dan transaksi pembayaran yang diterima pada {{ $periodLabel }}.</p></div>
                <form class="payment-room-filter" method="get" action="{{ route('finance') }}#detail-pembayaran">
                    <input type="hidden" name="from" value="{{ $from->toDateString() }}">
                    <input type="hidden" name="to" value="{{ $to->toDateString() }}">
                    <label><span>Tampilkan kamar</span><select name="payment_room" onchange="this.form.submit()"><option value="">Semua kamar</option>@foreach($paymentRoomSummary as $room)<option value="{{ $room->room_id }}" @selected($selectedPaymentRoom==$room->room_id)>Kamar #{{ $room->room_number }}</option>@endforeach</select></label>
                    @if($selectedPaymentRoom)<a class="btn secondary small" href="{{ route('finance',['from'=>$from->toDateString(),'to'=>$to->toDateString()]) }}#detail-pembayaran">Reset kamar</a>@endif
                </form>
            </div>

            <div class="room-payment-summary">
                @forelse($paymentRoomSummary as $room)
                    <a class="room-payment-card {{ $selectedPaymentRoom==$room->room_id?'active':'' }}" href="{{ route('finance',['from'=>$from->toDateString(),'to'=>$to->toDateString(),'payment_room'=>$room->room_id]) }}#detail-pembayaran">
                        <span>Kamar</span><strong>#{{ $room->room_number }}</strong><small>{{ number_format($room->transaction_count,0,',','.') }} transaksi</small><b>Rp {{ number_format($room->total_amount,0,',','.') }}</b>
                    </a>
                @empty
                    <div class="empty room-payment-empty">Belum ada pembayaran masuk pada periode ini.</div>
                @endforelse
            </div>

            <article class="panel payment-detail-panel">
                <div class="panelhead"><div><h2>Transaksi pembayaran</h2><p>{{ $selectedPaymentRoomNumber ? 'Kamar #'.$selectedPaymentRoomNumber : 'Semua kamar' }} · berdasarkan tanggal uang diterima</p></div><div class="payment-detail-total"><small>{{ number_format($paymentDetails->total(),0,',','.') }} transaksi</small><strong>Rp {{ number_format($paymentDetailTotal,0,',','.') }}</strong></div></div>
                <div class="tablewrap"><table class="table payment-detail-table"><thead><tr><th>Tanggal masuk</th><th>Kamar</th><th>Penghuni</th><th>Periode sewa</th><th>Metode</th><th>Nominal</th></tr></thead><tbody>
                    @forelse($paymentDetails as $payment)
                        <tr><td><strong>{{ $payment->paid_at->translatedFormat('d M Y') }}</strong><small>{{ $payment->is_historical?'Pembayaran historis':'Pembayaran reguler' }}</small></td><td><span class="room-number-pill">#{{ $payment->tenant->room->number }}</span></td><td><strong>{{ $payment->tenant->name }}</strong></td><td><strong>{{ $payment->period }}</strong><small>{{ match($payment->billing_cycle){'DAILY'=>'Harian','WEEKLY'=>'Mingguan',default=>'Bulanan'} }} · {{ $payment->period_count }} periode</small></td><td><span class="badge gray">{{ $payment->method }}</span></td><td><strong>Rp {{ number_format($payment->amount,0,',','.') }}</strong></td></tr>
                    @empty
                        <tr><td colspan="6" class="empty">Tidak ada transaksi pembayaran untuk filter ini.</td></tr>
                    @endforelse
                </tbody></table></div>
            </article>
            <x-ledger-pagination :paginator="$paymentDetails" />
        </section>

        <section class="finance-ratio-section">
            <div class="sectionhead"><div><h2>Rasio & indikator keuangan</h2><p>Dihitung otomatis dari data pendapatan dan pengeluaran periode terpilih.</p></div></div>
            <div class="ratio-grid">
                @foreach($ratios as $ratio)
                    @php($display=isset($ratio['money'])?'Rp '.number_format($ratio['money'],0,',','.'):(($ratio['value']??null)===null?'—':number_format($ratio['value'],isset($ratio['suffix'])?2:1,',','.').($ratio['suffix']??'%')))
                    <article class="ratio-card"><span>{{ $ratio['label'] }}</span><strong>{{ $display }}</strong><small>{{ $ratio['help'] }}</small></article>
                @endforeach
            </div>
        </section>

        <aside class="finance-note"><b>Catatan pembacaan laporan</b><p>Gross profit = pendapatan dikurangi kategori biaya langsung. Net profit = pendapatan dikurangi seluruh pengeluaran. Fixed cost adalah biaya yang tetap muncul secara berkala, sedangkan variable cost berubah mengikuti aktivitas usaha. Klasifikasi ini tidak dihitung dari okupansi. Laporan ini berbasis kas, bukan laporan akuntansi akrual atau laporan pajak.</p><span>Periode pembanding: {{ $previousFrom->translatedFormat('d M Y') }} – {{ $previousTo->translatedFormat('d M Y') }}</span></aside>
    </main>

    <nav class="mobile">
        <a href="{{ route('dashboard') }}"><b>⌂</b>Ringkasan</a>
        <a href="{{ route('dashboard',['tab'=>'payments']) }}"><b>↗</b>Pembayaran</a>
        <a href="{{ route('dashboard',['tab'=>'expenses']) }}"><b>↘</b>Pengeluaran</a>
        <a href="{{ route('finance') }}" class="active"><b>Rp</b>Keuangan</a>
        <a href="{{ route('imports.index') }}"><b>⇧</b>Import</a>
    </nav>
</div>
<script src="{{ asset('assets/rachaqakost.js') }}?v=20260817-import"></script>
</body>
</html>
