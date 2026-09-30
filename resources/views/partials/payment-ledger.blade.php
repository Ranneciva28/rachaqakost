<section class="sectionhead"><div><h2>Riwayat pembayaran</h2><p>Semua pembayaran reguler dan historis tersedia melalui pencarian serta pagination.</p></div><button class="btn" data-open="paymentModal">+ Catat bayar</button></section>
<article class="panel ledger-filter-panel">
<form class="ledger-filter" method="get" action="{{ route('dashboard') }}">
    <input type="hidden" name="tab" value="payments">
    <label class="ledger-search"><span>Cari data</span><input name="payment_search" value="{{ $paymentFilters['search'] }}" placeholder="Nama penghuni, kamar, periode, atau pencatat"></label>
    <div class="ledger-filter-grid">
        <label><span>Dari tanggal</span><input type="date" name="payment_from" value="{{ $paymentFilters['from'] }}"></label>
        <label><span>Sampai tanggal</span><input type="date" name="payment_to" value="{{ $paymentFilters['to'] }}"></label>
        <label><span>Nominal minimum</span><input type="number" name="payment_min" value="{{ $paymentFilters['min'] }}" min="0" placeholder="Rp 0"></label>
        <label><span>Nominal maksimum</span><input type="number" name="payment_max" value="{{ $paymentFilters['max'] }}" min="0" placeholder="Tanpa batas"></label>
        <label><span>Metode</span><select name="payment_method"><option value="">Semua metode</option>@foreach(['Transfer','Cash','QRIS'] as $method)<option value="{{ $method }}" @selected($paymentFilters['method']===$method)>{{ $method }}</option>@endforeach</select></label>
        <label><span>Siklus</span><select name="payment_cycle"><option value="">Semua siklus</option><option value="DAILY" @selected($paymentFilters['cycle']==='DAILY')>Harian</option><option value="WEEKLY" @selected($paymentFilters['cycle']==='WEEKLY')>Mingguan</option><option value="MONTHLY" @selected($paymentFilters['cycle']==='MONTHLY')>Bulanan</option></select></label>
        <label><span>Jenis pencatatan</span><select name="payment_kind"><option value="">Reguler + historis</option><option value="REGULAR" @selected($paymentFilters['kind']==='REGULAR')>Reguler</option><option value="HISTORICAL" @selected($paymentFilters['kind']==='HISTORICAL')>Historis</option></select></label>
        <label><span>Sumber</span><select name="payment_source"><option value="">Manual + import</option><option value="MANUAL" @selected($paymentFilters['source']==='MANUAL')>Input manual</option><option value="IMPORT" @selected($paymentFilters['source']==='IMPORT')>Batch import</option></select></label>
        <label><span>Baris per halaman</span><select name="payment_per_page">@foreach($ledgerPageSizes as $size)<option value="{{ $size }}" @selected($paymentFilters['per_page']===$size)>{{ number_format($size,0,',','.') }} baris</option>@endforeach</select></label>
    </div>
    <div class="ledger-filter-actions"><button class="btn">Terapkan filter</button><a class="btn secondary" href="{{ route('dashboard',['tab'=>'payments']) }}">Reset</a></div>
</form>
</article>
<div class="ledger-result-summary"><span><b>{{ number_format($payments->total(),0,',','.') }}</b> transaksi ditemukan</span><span>Total nominal hasil filter <b>Rp {{ number_format($paymentFilteredTotal,0,',','.') }}</b></span></div>
<article class="panel tablewrap"><table class="table payment-ledger-table">
<thead><tr><th>Tanggal</th><th>Penghuni</th><th>Periode</th><th>Metode</th><th>Pencatat</th><th>Nominal</th>@if(auth()->user()->isOwner())<th>Aksi</th>@endif</tr></thead>
<tbody>
@forelse($payments as $p)
<tr>
    <td>{{ $p->paid_at->format('d M Y') }}</td>
    <td><strong>{{ $p->tenant->name }}</strong><small>Kamar #{{ $p->tenant->room->number }}</small></td>
    <td><strong>{{ $p->period }}</strong><small>{{ match($p->billing_cycle){'DAILY'=>'Harian','WEEKLY'=>'Mingguan',default=>'Bulanan'} }} · {{ $p->period_count }} periode @if($p->is_historical)<span class="history-pill">Historis</span>@endif @if($p->import_batch_id)<span class="history-pill import-pill">Import</span>@endif</small></td>
    <td><span class="badge gray">{{ $p->method }}</span></td>
    <td>{{ $p->recorder->name }}</td>
    <td><strong>Rp {{ number_format($p->amount,0,',','.') }}</strong></td>
    @if(auth()->user()->isOwner())
    <td class="payment-action-cell">
        @if($p->import_batch_id)
            <span class="payment-source-lock">Kelola melalui batch import</span>
        @else
            <div class="payment-actions">
                <button type="button" class="btn secondary small" data-edit-payment
                    data-action="{{ route('payments.update',$p) }}"
                    data-name="{{ $p->tenant->name }} · Kamar #{{ $p->tenant->room->number }}"
                    data-kind="{{ $p->is_historical?'HISTORICAL':'REGULAR' }}"
                    data-amount="{{ (int)$p->amount }}"
                    data-paid-at="{{ $p->paid_at->toDateString() }}"
                    data-method="{{ $p->method }}"
                    data-cycle="{{ $p->billing_cycle }}"
                    data-periods="{{ max(1,(int)$p->period_count) }}"
                    data-period-start="{{ $p->coverage_start?->toDateString() }}">Edit</button>
                <form method="post" action="{{ route('payments.destroy',$p) }}" data-confirm="Hapus pembayaran {{ $p->tenant->name }} sebesar Rp {{ number_format($p->amount,0,',','.') }}? Jatuh tempo penghuni akan dihitung ulang.">
                    @csrf @method('DELETE')
                    <button class="btn danger small">Hapus</button>
                </form>
            </div>
        @endif
    </td>
    @endif
</tr>
@empty
<tr><td colspan="{{ auth()->user()->isOwner()?7:6 }}" class="empty">Tidak ada pembayaran yang cocok dengan filter.</td></tr>
@endforelse
</tbody>
</table></article>
<x-ledger-pagination :paginator="$payments" />

@if(auth()->user()->isOwner())
<dialog class="modal" id="paymentEditModal">
    <div class="modalhead"><div><h2>Edit pembayaran</h2><p id="paymentEditSubtitle">Perbarui transaksi dan periode tagihan.</p></div><button class="close" type="button" data-close>×</button></div>
    <form class="form" id="paymentEditForm" method="post">
        @csrf @method('PATCH')
        <div class="payment-edit-context"><span id="paymentEditTenant"></span><b id="paymentEditKind"></b></div>
        <div class="formgrid">
            <div class="field"><label>Nominal</label><div class="currency-input"><span>Rp</span><input type="text" name="amount" data-currency inputmode="numeric" autocomplete="off" required></div></div>
            <div class="field"><label>Tanggal bayar</label><input type="date" name="paid_at" required></div>
            <div class="field"><label>Metode</label><select name="method" required><option>Transfer</option><option>QRIS</option><option>Cash</option></select></div>
            <div class="field"><label>Siklus pembayaran</label><select name="billing_cycle" id="paymentEditCycle" required><option value="DAILY">Harian</option><option value="WEEKLY">Mingguan</option><option value="MONTHLY">Bulanan</option></select></div>
            <div class="field"><label id="paymentEditPeriodsLabel">Jumlah periode</label><input type="number" name="periods" id="paymentEditPeriods" min="1" max="365" required></div>
            <div class="field" id="paymentEditHistoricalStart" hidden><label>Periode mulai histori</label><input type="date" name="period_start"></div>
        </div>
        <small class="payment-edit-note" id="paymentEditNote">Periode sesudah transaksi ini dan jatuh tempo penghuni akan dihitung ulang otomatis.</small>
        <div class="modalfoot"><button type="button" class="btn secondary" data-close>Batal</button><button class="btn">Simpan perubahan</button></div>
    </form>
</dialog>
@endif
