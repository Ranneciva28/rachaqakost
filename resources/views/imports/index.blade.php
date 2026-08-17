<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
    <title>RachaqaKost — Import Data</title>
    <link rel="stylesheet" href="{{ asset('assets/rachaqakost.css') }}"><link rel="stylesheet" href="{{ asset('assets/rachaqakost-fixes.css') }}?v=20260817-import">
</head>
<body>
@php($tabs=['dashboard'=>['⌂','Ringkasan'],'rooms'=>['▦','Kamar'],'tenants'=>['◎','Penghuni'],'payments'=>['↗','Pembayaran'],'expenses'=>['↘','Pengeluaran'],'maintenance'=>['◇','Maintenance'],'users'=>['♙','Tim']])
<div class="shell"><aside class="sidebar"><div class="brand"><span class="brandmark">K</span><div><b>RachaqaKost</b><small>Operational OS</small></div></div><nav class="nav">@foreach($tabs as $key=>$tab)<a href="{{ route('dashboard',['tab'=>$key]) }}"><span class="ico">{{ $tab[0] }}</span>{{ $tab[1] }}</a>@endforeach<div class="nav-separator"></div><a href="{{ route('finance') }}"><span class="ico">Rp</span>Keuangan</a><a href="{{ route('imports.index') }}" class="active"><span class="ico">⇧</span>Import Data</a></nav><div class="profile"><b>{{ auth()->user()->name }}</b><small>Owner / Admin</small><form method="post" action="{{ route('logout') }}">@csrf<button class="logout">Keluar dari workspace</button></form></div></aside>
<main class="main import-main"><header class="top import-top"><div><span class="eyebrow">DATA ONBOARDING</span><h1>Import data historis</h1><p>Ubah foto buku atau CSV menjadi draft transaksi yang dapat diperiksa sebelum masuk pembukuan.</p></div></header>
@if(session('success'))<div class="toast">✓ {{ session('success') }}</div>@endif @if($errors->any())<div class="toast error">{{ $errors->first() }}</div>@endif

<section class="import-guard"><span>✓</span><div><b>Jatuh tempo tetap aman</b><p>Semua transaksi dari menu ini ditandai sebagai histori dan tidak pernah memajukan <code>next_due</code> penghuni. File foto dikirim ke OpenAI API untuk dibaca dan tidak disimpan oleh aplikasi RachaqaKost.</p></div></section>

<section class="import-upload-grid">
    <article class="panel import-upload-card"><div class="import-upload-icon">▧</div><div><span class="eyebrow">VISION AI</span><h2>Foto buku transaksi</h2><p>Ambil sampai 4 foto sekaligus. Tulisan akan diubah menjadi draft baris pendapatan atau pengeluaran.</p></div>
        @unless($visionReady)<div class="config-warning"><b>Vision belum aktif</b><span>Tambahkan <code>OPENAI_API_KEY</code> di Railway Variables.</span></div>@endunless
        <form class="form import-upload-form" method="post" action="{{ route('imports.images') }}" enctype="multipart/form-data">@csrf
            <label class="file-drop"><input type="file" name="images[]" accept="image/jpeg,image/png,image/webp,image/heic,image/heif" multiple required @disabled(!$visionReady)><b>Pilih atau foto halaman buku</b><small>JPG, PNG, WEBP, atau HEIC · maksimal 12 MB/foto</small></label>
            <div class="formgrid"><div class="field"><label>Isi buku</label><select name="ledger_kind"><option value="MIXED">Campuran pemasukan & pengeluaran</option><option value="PAYMENT">Pembayaran sewa saja</option><option value="EXPENSE">Pengeluaran saja</option></select></div><div class="field"><label>Tahun default</label><input type="number" name="default_year" value="{{ today()->year }}" min="2000" max="2100" required></div></div>
            <button class="btn" @disabled(!$visionReady)>Baca foto & buat draft</button>
        </form>
    </article>
    <article class="panel import-upload-card"><div class="import-upload-icon csv">CSV</div><div><span class="eyebrow">BULK FILE</span><h2>Spreadsheet / CSV</h2><p>Gunakan template agar ratusan transaksi dapat dimuat sekaligus dan tetap melewati layar validasi.</p></div>
        <a class="btn secondary import-template" href="{{ route('imports.template') }}">↓ Download template CSV</a>
        <form class="form import-upload-form" method="post" action="{{ route('imports.csv') }}" enctype="multipart/form-data">@csrf
            <label class="file-drop"><input type="file" name="csv" accept=".csv,text/csv" required><b>Pilih file CSV</b><small>Maksimal 1.000 baris · ukuran 10 MB</small></label>
            <button class="btn">Muat CSV & buat draft</button>
        </form>
    </article>
</section>

<section class="sectionhead import-history-head"><div><h2>Riwayat batch</h2><p>Draft dapat dilanjutkan; batch selesai disimpan sebagai audit trail.</p></div></section>
<article class="panel tablewrap"><table class="table"><thead><tr><th>Batch</th><th>Sumber</th><th>Status</th><th>Baris</th><th>Dibuat</th><th>Aksi</th></tr></thead><tbody>@forelse($batches as $batch)<tr><td><strong>#{{ str_pad($batch->id,4,'0',STR_PAD_LEFT) }}</strong><small>{{ collect($batch->original_names)->join(', ') }}</small></td><td><span class="badge {{ $batch->source_type==='IMAGE'?'':'gray' }}">{{ $batch->source_type==='IMAGE'?'Foto AI':'CSV' }}</span></td><td><span class="batch-status {{ strtolower($batch->status) }}">{{ match($batch->status){'DRAFT'=>'Perlu review','COMPLETED'=>'Selesai',default=>'Gagal'} }}</span>@if($batch->error_message)<small>{{ $batch->error_message }}</small>@endif</td><td><strong>{{ $batch->rows_count }}</strong><small>{{ $batch->valid_rows }} valid · {{ $batch->imported_rows }} masuk</small></td><td>{{ $batch->created_at->format('d M Y H:i') }}<small>{{ $batch->uploader->name }}</small></td><td><a class="btn secondary small" href="{{ route('imports.show',$batch) }}">{{ $batch->status==='DRAFT'?'Review':'Lihat' }}</a></td></tr>@empty<tr><td colspan="6" class="empty">Belum ada batch import.</td></tr>@endforelse</tbody></table></article>
</main><nav class="mobile"><a href="{{ route('dashboard') }}"><b>⌂</b>Ringkasan</a><a href="{{ route('finance') }}"><b>Rp</b>Keuangan</a><a href="{{ route('imports.index') }}" class="active"><b>⇧</b>Import</a></nav></div>
<script src="{{ asset('assets/rachaqakost.js') }}?v=20260817-import"></script></body></html>
