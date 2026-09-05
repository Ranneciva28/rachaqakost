<!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><title>Attachment Formulir — {{ $tenant->name }}</title><link rel="stylesheet" href="{{ asset('assets/tenant-form.css') }}?v=20260905"></head>
<body>
<main class="public-form-shell attachment-shell">
    <div class="attachment-nav"><a href="{{ route('dashboard',['tab'=>'tenants']) }}">← Kembali ke Data Penghuni</a><button type="button" onclick="window.print()">Cetak / Simpan PDF</button></div>
    <header class="public-brand"><span>K</span><div><b>RachaqaKost</b><small>Attachment Formulir Data Penghuni</small></div></header>
    @if(session('success'))<div class="notice success">✓ {{ session('success') }}</div>@endif
    <section class="form-hero"><div><span class="eyebrow">KAMAR #{{ $tenant->room->number }}</span><h1>{{ $form->full_name }}</h1><p>Dikirim {{ $form->submitted_at->translatedFormat('d F Y, H:i') }}</p></div><span class="status-chip {{ $form->statusBadge() }}">{{ $form->statusLabel() }}</span></section>
    @php($editable=false)
    <div class="public-form">@include('tenant-forms.form-fields')</div>
    @if(auth()->user()->isOwner())
    <section class="approval-panel">
        <div><b>Kontrol Owner/Admin</b><p>Validasi mengunci formulir sebagai data sah. Revisi membuka link yang sama agar penghuni dapat memperbaiki data.</p></div>
        <div class="approval-actions">
            @if($form->status==='PENDING_APPROVAL')<form method="post" action="{{ route('tenant-form.validate',$tenant) }}">@csrf @method('PATCH')<button class="approve">Validasi Formulir</button></form>@endif
            @if($form->status!=='REVISION')<form method="post" action="{{ route('tenant-form.revision',$tenant) }}" onsubmit="return confirm('Buka kembali formulir untuk direvisi penghuni? Status validasi sebelumnya akan dibatalkan.')">@csrf @method('PATCH')<button class="revision-button">Revisi Formulir</button></form>@endif
        </div>
    </section>
    @endif
    @if($form->validated_at)<div class="validation-proof">Divalidasi oleh {{ $form->validator?->name ?? 'Owner/Admin' }} pada {{ $form->validated_at->translatedFormat('d F Y, H:i') }}</div>@endif
</main>
</body></html>
