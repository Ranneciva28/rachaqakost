<!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="description" content="RachaqaKost — pilihan kamar kost harian, mingguan, dan bulanan."><title>RachaqaKost — Hunian Nyaman</title>@include('partials.branding-head')<link rel="stylesheet" href="{{ asset('assets/home.css') }}?v=20260907-thumbnail"><link rel="stylesheet" href="{{ asset('assets/home-waiting-list.css') }}?v=20260907-thumbnail-fit"></head>
<body>
@php
    $waNumber=preg_replace('/\D+/','',$settings['site_admin_whatsapp']);if(str_starts_with($waNumber,'0'))$waNumber='62'.substr($waNumber,1);
    $wa=function($category='kamar')use($waNumber,$settings){$message=str_replace('{kategori}',$category,$settings['site_whatsapp_message']);return 'https://wa.me/'.$waNumber.'?text='.rawurlencode($message);};
@endphp
<header class="site-nav"><a class="site-brand" href="#top"><span>@if($brandLogo)<img src="{{ route('media.public',$brandLogo) }}" alt="Logo RachaqaKost">@else K @endif</span><b>RachaqaKost</b></a><nav><a href="#pilihan-kamar">Pilihan kamar</a><a href="#tentang">Tentang</a><a href="#lokasi">Lokasi</a><a class="nav-wa" href="{{ $wa() }}" target="_blank" rel="noopener">Chat WhatsApp</a></nav></header>
<main id="top">
<section class="hero {{ $hero?'has-photo':'' }}" @if($hero) style="--hero:url('{{ route('media.public',$hero) }}')" @endif><div class="hero-overlay"></div><div class="hero-copy"><span class="eyebrow">RACHAQAKOST</span><h1>{{ $settings['site_hero_title'] }}</h1><p>{{ $settings['site_hero_subtitle'] }}</p><div class="hero-actions"><a class="primary" href="#pilihan-kamar">Lihat pilihan kamar</a><a class="secondary" href="{{ $wa() }}" target="_blank" rel="noopener">Tanya Admin via WA</a></div></div><div class="hero-note"><span>Fleksibel</span><b>Harian · Mingguan · Bulanan</b></div></section>
<section class="intro" id="tentang"><span class="eyebrow">TENTANG KAMI</span><h2>Tempat pulang yang dikelola dengan perhatian.</h2><p>{{ $settings['site_about'] }}</p><div class="facility-list">@foreach(preg_split('/\r\n|\r|\n/',$settings['site_facilities']) as $facility)@if(trim($facility)!=='')<span>✓ {{ trim($facility) }}</span>@endif @endforeach</div></section>
<section class="rooms" id="pilihan-kamar">
    <div class="section-title"><div><span class="eyebrow">PILIHAN KAMAR</span><h2>Pilih kategori sesuai kebutuhan.</h2></div><p>Harga dan kuota kamar tersinkron langsung dengan panel pengelola.</p></div>
    @if(session('availability_notice'))<div class="availability-notice">{{ session('availability_notice') }}</div>@endif
    <div class="room-cards">
    @forelse($categories as $category)
        @php
            $availableRooms=$category->rooms->where('status','KOSONG');
            $availableCount=$availableRooms->count();
            $floors=collect([1,2])->merge($category->rooms->pluck('floor'))->unique()->sort()->values();
        @endphp
        <article class="room-card">
            <div class="room-gallery">@if($category->thumbnail)<img class="room-cover" src="{{ route('media.public',$category->thumbnail) }}" alt="Thumbnail kamar {{ $category->name }}" loading="lazy">@else<div class="room-empty"><span>K</span><small>Foto {{ $category->name }} segera hadir</small></div>@endif</div>
            <div class="room-copy">
                <div class="room-head"><div><small>KATEGORI</small><h3>{{ $category->name }}</h3></div><span>{{ $category->rooms_count }} kamar</span></div>
                <div class="availability {{ $availableCount===0?'is-full':'' }}">
                    <div><small>KUOTA KAMAR</small><strong>{{ $availableCount===0?'Penuh':$availableCount.' Kamar Tersedia' }}</strong></div>
                    <div class="floor-quota">@foreach($floors as $floor)<span>Lantai {{ $floor }} <b>{{ $availableRooms->where('floor',$floor)->count() }}</b></span>@endforeach</div>
                </div>
                <div class="prices"><div><small>Harian</small><b>Rp {{ number_format($category->daily_price,0,',','.') }}</b></div><div><small>Mingguan</small><b>Rp {{ number_format($category->weekly_price,0,',','.') }}</b></div><div class="featured"><small>Bulanan</small><b>Rp {{ number_format($category->monthly_price,0,',','.') }}</b></div></div>
                <div class="room-actions {{ $availableCount===0?'is-full':'' }}">
                    <a class="facility-button" href="{{ route('categories.facilities',$category) }}"><span class="facility-button-icon" aria-hidden="true">✦</span><span class="facility-button-label"><small>DETAIL KAMAR</small><b>Lihat Fasilitas</b></span><span class="facility-button-arrow" aria-hidden="true">→</span></a>
                    @if($availableCount===0)
                        <a class="waiting-button" href="{{ route('waiting-list.show',$category) }}">Isi Waiting List →</a>
                    @else
                        <a class="interest" href="{{ $wa($category->name) }}" target="_blank" rel="noopener">Saya Minat →</a>
                    @endif
                </div>
            </div>
        </article>
    @empty<div class="empty-categories">Pilihan kamar sedang diperbarui. Hubungi Admin untuk informasi ketersediaan.</div>@endforelse
    </div>
</section>
<section class="location" id="lokasi"><div><span class="eyebrow">LOKASI</span><h2>Mudah ditanyakan, mudah ditemukan.</h2><p>{{ $settings['site_address'] }}</p></div><a href="{{ $wa() }}" target="_blank" rel="noopener">Hubungi Admin RachaqaKost</a></section>
</main><footer><a class="site-brand" href="#top"><span>@if($brandLogo)<img src="{{ route('media.public',$brandLogo) }}" alt="Logo RachaqaKost">@else K @endif</span><b>RachaqaKost</b></a><p>Hunian nyaman, pengelolaan terpercaya.</p><a class="admin-link" href="{{ route('dashboard') }}">Panel pengelola</a></footer>
</body></html>
