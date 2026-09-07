<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="description" content="Fasilitas dan ketersediaan kamar kategori {{ $category->name }} di RachaqaKost.">
    <title>Fasilitas {{ $category->name }} — RachaqaKost</title>
    @include('partials.branding-head')
    <link rel="stylesheet" href="{{ asset('assets/category-facilities.css') }}?v=20260907-gallery">
    <link rel="stylesheet" href="{{ asset('assets/category-gallery.css') }}?v=20260907-contain">
</head>
<body>
@php
    $availableRooms=$category->rooms->where('status','KOSONG');
    $availableCount=$availableRooms->count();
    $floors=collect([1,2])->merge($category->rooms->pluck('floor'))->unique()->sort()->values();
    $facilities=collect($category->facilities??[])->map(fn($facility)=>trim((string)$facility))->filter()->values();
    $waNumber=preg_replace('/\D+/','',$settings['site_admin_whatsapp']);if(str_starts_with($waNumber,'0'))$waNumber='62'.substr($waNumber,1);
    $waMessage=str_replace('{kategori}',$category->name,$settings['site_whatsapp_message']);
    $waUrl='https://wa.me/'.$waNumber.'?text='.rawurlencode($waMessage);
@endphp
<header class="detail-nav">
    <a class="detail-brand" href="{{ route('home') }}"><span>@if($brandLogo)<img src="{{ route('media.public',$brandLogo) }}" alt="Logo RachaqaKost">@else K @endif</span><div><b>RachaqaKost</b><small>Detail Kategori Kamar</small></div></a>
    <a class="back-home" href="{{ route('home') }}#pilihan-kamar">← Kembali ke Homepage</a>
</header>

<main>
    <section class="detail-hero">
        <div class="detail-heading"><span class="eyebrow">KATEGORI KAMAR</span><h1>{{ $category->name }}</h1><p>Lihat fasilitas, pilihan harga, foto kamar, dan ketersediaan terbaru sebelum menentukan pilihan.</p></div>
        <div class="detail-availability {{ $availableCount===0?'is-full':'' }}"><small>KETERSEDIAAN TERKINI</small><strong>{{ $availableCount===0?'Penuh':$availableCount.' Kamar Tersedia' }}</strong><div>@foreach($floors as $floor)<span>Lantai {{ $floor }} <b>{{ $availableRooms->where('floor',$floor)->count() }}</b></span>@endforeach</div></div>
    </section>

    <section class="detail-layout">
        <div class="detail-gallery">
            @if($category->photos->isNotEmpty())
                @foreach($category->photos as $photo)<button type="button" class="detail-gallery-item {{ $loop->first?'detail-cover':'' }}" data-gallery-index="{{ $loop->index }}" data-gallery-src="{{ route('media.public',$photo) }}" data-gallery-alt="Foto {{ $loop->iteration }} kategori {{ $category->name }}"><img src="{{ route('media.public',$photo) }}" alt="Foto {{ $loop->iteration }} kategori {{ $category->name }}" loading="lazy"><span>Lihat foto</span></button>@endforeach
            @else
                <div class="detail-photo-empty"><span>K</span><b>Foto {{ $category->name }} segera hadir</b><small>Hubungi Admin untuk meminta foto terbaru.</small></div>
            @endif
        </div>

        <aside class="detail-summary">
            <span class="eyebrow">PILIHAN SEWA</span><h2>Harga {{ $category->name }}</h2>
            <div class="detail-prices"><div><small>Harian</small><b>Rp {{ number_format($category->daily_price,0,',','.') }}</b></div><div><small>Mingguan</small><b>Rp {{ number_format($category->weekly_price,0,',','.') }}</b></div><div class="featured"><small>Bulanan</small><b>Rp {{ number_format($category->monthly_price,0,',','.') }}</b></div></div>
            @if($availableCount===0)<a class="detail-primary" href="{{ route('waiting-list.show',$category) }}">Isi Form Waiting List</a>@else<a class="detail-primary" href="{{ $waUrl }}" target="_blank" rel="noopener">Tanya Ketersediaan via WhatsApp</a>@endif
            <a class="detail-secondary" href="{{ route('home') }}#pilihan-kamar">Lihat Kategori Lain</a>
        </aside>
    </section>

    <section class="category-facilities">
        <div class="facilities-heading"><span class="eyebrow">FASILITAS KATEGORI</span><h2>Yang tersedia di {{ $category->name }}</h2><p>Fasilitas berikut diatur langsung oleh pengelola RachaqaKost.</p></div>
        @if($facilities->isNotEmpty())<div class="facilities-grid">@foreach($facilities as $facility)<article><span>✓</span><b>{{ $facility }}</b></article>@endforeach</div>@else<div class="facilities-empty"><b>Detail fasilitas sedang diperbarui.</b><p>Silakan hubungi Admin RachaqaKost untuk mendapatkan informasi fasilitas kategori ini.</p></div>@endif
    </section>

    <section class="detail-bottom"><div><span class="eyebrow">BUTUH INFORMASI LAIN?</span><h2>Tim RachaqaKost siap membantu.</h2></div><div><a href="{{ route('home') }}#pilihan-kamar">← Kembali ke Homepage</a><a class="whatsapp" href="{{ $waUrl }}" target="_blank" rel="noopener">Chat Admin</a></div></section>
</main>

<footer><a class="detail-brand" href="{{ route('home') }}"><span>@if($brandLogo)<img src="{{ route('media.public',$brandLogo) }}" alt="Logo RachaqaKost">@else K @endif</span><div><b>RachaqaKost</b><small>Hunian nyaman, pengelolaan terpercaya.</small></div></a></footer>
@if($category->photos->isNotEmpty())
<dialog class="photo-lightbox" id="categoryPhotoLightbox" aria-label="Galeri foto {{ $category->name }}">
    <button type="button" class="lightbox-close" data-lightbox-close aria-label="Tutup galeri">×</button>
    <button type="button" class="lightbox-nav lightbox-prev" data-lightbox-prev aria-label="Foto sebelumnya">←</button>
    <figure><img src="" alt=""><figcaption><span id="lightboxCaption"></span><b id="lightboxCounter"></b></figcaption></figure>
    <button type="button" class="lightbox-nav lightbox-next" data-lightbox-next aria-label="Foto berikutnya">→</button>
</dialog>
<script src="{{ asset('assets/category-facilities.js') }}?v=20260907"></script>
@endif
</body>
</html>
