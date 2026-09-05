<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive"><meta name="referrer" content="no-referrer">
    <title>Formulir Data Penghuni — RachaqaKost</title>
    <link rel="stylesheet" href="{{ asset('assets/tenant-form.css') }}?v=20260905">
</head>
<body>
@php($editable=!$form||$form->status==='REVISION')
<main class="public-form-shell">
    <header class="public-brand"><span>K</span><div><b>RachaqaKost</b><small>Formulir Data Penghuni</small></div></header>
    <section class="form-hero">
        <div><span class="eyebrow">KAMAR #{{ $tenant->room->number }}</span><h1>Data penghuni</h1><p>Lengkapi data berikut dengan benar. Setelah dikirim, formulir akan dikunci dan diperiksa oleh pengelola.</p></div>
        <span class="status-chip {{ $form?->statusBadge() ?? 'gray' }}">{{ $form?->statusLabel() ?? 'Formulir Belum Diisi' }}</span>
    </section>
    @if(session('success'))<div class="notice success">✓ {{ session('success') }}</div>@endif
    @if($errors->any())<div class="notice error"><b>Periksa kembali data berikut:</b><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    @if(!$editable)
        <div class="notice locked"><b>Formulir terkunci.</b> Data tetap dapat dilihat, tetapi hanya dapat diubah setelah Owner/Admin membuka mode Revisi Formulir.</div>
    @elseif($form?->status==='REVISION')
        <div class="notice revision"><b>Revisi dibuka.</b> Perbaiki data yang diperlukan lalu kirim kembali untuk divalidasi ulang.</div>
    @endif

    <form method="post" action="{{ route('tenant-form.submit',$tenant->form_token) }}" enctype="multipart/form-data" class="public-form">@csrf
        @include('tenant-forms.form-fields')
        @if($editable)
        <label class="declaration"><input type="checkbox" required><span>Saya menyatakan seluruh data yang saya isi benar dan dapat dipertanggungjawabkan.</span></label>
        <div class="public-submit"><button>Kirim formulir</button><small>Formulir tidak dapat diedit kembali setelah dikirim tanpa izin Owner/Admin.</small></div>
        @endif
    </form>
    <footer>Data hanya digunakan untuk administrasi penghuni RachaqaKost.</footer>
</main><script src="{{ asset('assets/confirm-actions.js') }}?v=20260905-2"></script>
</body></html>
