<section class="sectionhead"><div><h2>Pengeluaran operasional</h2><p>Semua biaya manual dan hasil import tersedia melalui pencarian serta pagination.</p></div><button class="btn" data-open="expenseModal">+ Pengeluaran</button></section>
<article class="panel ledger-filter-panel">
<form class="ledger-filter" method="get" action="{{ route('dashboard') }}">
    <input type="hidden" name="tab" value="expenses">
    <label class="ledger-search"><span>Cari data</span><input name="expense_search" value="{{ $expenseFilters['search'] }}" placeholder="Nama pengeluaran, kategori, catatan, atau pencatat"></label>
    <div class="ledger-filter-grid">
        <label><span>Dari tanggal</span><input type="date" name="expense_from" value="{{ $expenseFilters['from'] }}"></label>
        <label><span>Sampai tanggal</span><input type="date" name="expense_to" value="{{ $expenseFilters['to'] }}"></label>
        <label><span>Nominal minimum</span><input type="number" name="expense_min" value="{{ $expenseFilters['min'] }}" min="0" placeholder="Rp 0"></label>
        <label><span>Nominal maksimum</span><input type="number" name="expense_max" value="{{ $expenseFilters['max'] }}" min="0" placeholder="Tanpa batas"></label>
        <label><span>Kategori</span><select name="expense_category"><option value="">Semua kategori</option>@foreach($expenseCategories as $category)<option value="{{ $category->name }}" @selected($expenseFilters['category']===$category->name)>{{ $category->name }}</option>@endforeach</select></label>
        <label><span>Fungsi biaya</span><select name="expense_cost_type"><option value="">Semua fungsi</option><option value="DIRECT" @selected($expenseFilters['cost_type']==='DIRECT')>Biaya langsung</option><option value="OPERATING" @selected($expenseFilters['cost_type']==='OPERATING')>Biaya operasional</option></select></label>
        <label><span>Perilaku biaya</span><select name="expense_cost_behavior"><option value="">Fixed + variable</option><option value="FIXED" @selected($expenseFilters['cost_behavior']==='FIXED')>Fixed cost</option><option value="VARIABLE" @selected($expenseFilters['cost_behavior']==='VARIABLE')>Variable cost</option></select></label>
        <label><span>Sumber</span><select name="expense_source"><option value="">Manual + import</option><option value="MANUAL" @selected($expenseFilters['source']==='MANUAL')>Input manual</option><option value="IMPORT" @selected($expenseFilters['source']==='IMPORT')>Batch import</option></select></label>
        <label><span>Baris per halaman</span><select name="expense_per_page">@foreach($ledgerPageSizes as $size)<option value="{{ $size }}" @selected($expenseFilters['per_page']===$size)>{{ number_format($size,0,',','.') }} baris</option>@endforeach</select></label>
    </div>
    <div class="ledger-filter-actions"><button class="btn">Terapkan filter</button><a class="btn secondary" href="{{ route('dashboard',['tab'=>'expenses']) }}">Reset</a></div>
</form>
</article>
<div class="ledger-result-summary"><span><b>{{ number_format($expenses->total(),0,',','.') }}</b> pengeluaran ditemukan</span><span>Total nominal hasil filter <b>Rp {{ number_format($expenseFilteredTotal,0,',','.') }}</b></span></div>
<article class="panel tablewrap"><table class="table"><thead><tr><th>Tanggal</th><th>Pengeluaran</th><th>Kategori</th><th>Pencatat</th><th>Nominal</th></tr></thead><tbody>@forelse($expenses as $e)@php($expenseColor=$expenseCategories->firstWhere('name',$e->category)?->color ?? '#DF8A42')<tr><td>{{ $e->spent_at->format('d M Y') }}</td><td><strong>{{ $e->title }}</strong><small>{{ $e->notes?:'Tanpa catatan' }} @if($e->import_batch_id)<span class="history-pill import-pill">Import</span>@endif</small></td><td><span class="expense-label" style="--accent:{{ $expenseColor }}">{{ $e->category }}</span></td><td>{{ $e->recorder->name }}</td><td><strong>Rp {{ number_format($e->amount,0,',','.') }}</strong></td></tr>@empty<tr><td colspan="5" class="empty">Tidak ada pengeluaran yang cocok dengan filter.</td></tr>@endforelse</tbody></table></article>
<x-ledger-pagination :paginator="$expenses" />
