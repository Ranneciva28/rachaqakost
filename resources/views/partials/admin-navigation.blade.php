@php
$ownerOnlyTabs=$ownerOnlyTabs??['form-builder','website','users'];
$titles=array_replace(['dashboard'=>'Ringkasan operasional','rooms'=>'Kamar & kategori','tenants'=>'Data penghuni','maintenance'=>'Maintenance kamar','form-drafts'=>'Draft Formulir','form-builder'=>'Atur Formulir','waiting-list'=>'Waiting List','payments'=>'Pembayaran','expenses'=>'Pengeluaran','website'=>'Homepage','users'=>'Akses Tim','finance'=>'Laporan Keuangan','imports'=>'Import Data'],$titles??[]);
$navGroups=[
    'Operasional'=>['rooms'=>['▦','Kamar'],'tenants'=>['◎','Penghuni'],'maintenance'=>['◇','Maintenance']],
    'Formulir & Prospek'=>['form-drafts'=>['▤','Draft Formulir'],'form-builder'=>['⚙','Atur Formulir'],'waiting-list'=>['◌','Waiting List']],
    'Transaksi'=>['payments'=>['↗','Pembayaran'],'expenses'=>['↘','Pengeluaran']],
    'Website & Sistem'=>['website'=>['◉','Homepage'],'users'=>['♙','Tim']],
];
@endphp
<aside class="sidebar">
    <div class="brand"><span class="brandmark">@if($brandLogo)<img src="{{ route('media.public',$brandLogo) }}" alt="Logo">@else K @endif</span><div><b>RachaqaKost</b><small>Operational OS</small></div></div>
    <nav class="nav compact-nav">
        <a href="{{ route('dashboard') }}" class="{{ $activeTab==='dashboard'?'active':'' }}"><span class="ico">⌂</span>Ringkasan</a>
        @foreach($navGroups as $groupLabel=>$items)
            @php($visibleItems=collect($items)->reject(fn($item,$key)=>in_array($key,$ownerOnlyTabs,true)&&!auth()->user()->isOwner()))
            @if($visibleItems->isNotEmpty())
            <details class="nav-group" @if($visibleItems->keys()->contains($activeTab)) open @endif>
                <summary><span>{{ $groupLabel }}</span><i>⌄</i></summary>
                <div>@foreach($visibleItems as $key=>$item)<a href="{{ route('dashboard',['tab'=>$key]) }}" class="{{ $activeTab===$key?'active':'' }}"><span class="ico">{{ $item[0] }}</span>{{ $item[1] }}</a>@endforeach</div>
            </details>
            @endif
        @endforeach
        @if(auth()->user()->isOwner())
        <details class="nav-group external-group" @if(in_array($activeTab,['finance','imports'],true)) open @endif>
            <summary><span>Data & Laporan</span><i>⌄</i></summary><div><a href="{{ route('finance') }}" class="{{ $activeTab==='finance'?'active':'' }}"><span class="ico">Rp</span>Keuangan</a><a href="{{ route('imports.index') }}" class="{{ $activeTab==='imports'?'active':'' }}"><span class="ico">⇧</span>Import Data</a><a href="{{ route('home') }}" target="_blank"><span class="ico">↗</span>Lihat Website</a></div>
        </details>
        @endif
    </nav>
    <div class="profile"><b>{{ auth()->user()->name }}</b><small>{{ auth()->user()->role==='OWNER'?'Owner / Admin':'Penjaga Kos' }}</small><form method="post" action="{{ route('logout') }}">@csrf<button class="logout">Keluar dari workspace</button></form></div>
</aside>
<header class="mobile-nav-bar">
    <button type="button" class="mobile-nav-trigger" data-mobile-nav-open aria-controls="mobileAdminNavigation" aria-expanded="false" aria-label="Buka menu utama"><span></span><span></span><span></span></button>
    <div><small>RachaqaKost</small><strong>{{ $titles[$activeTab] }}</strong></div>
</header>
<button type="button" class="mobile-nav-backdrop" data-mobile-nav-close tabindex="-1" aria-label="Tutup menu"></button>
<aside class="mobile-nav-drawer" id="mobileAdminNavigation" aria-hidden="true">
    <div class="mobile-nav-head">
        <a class="mobile-nav-brand" href="{{ route('dashboard') }}"><span class="brandmark">@if($brandLogo)<img src="{{ route('media.public',$brandLogo) }}" alt="Logo">@else K @endif</span><span><b>RachaqaKost</b><small>Operational OS</small></span></a>
        <button type="button" class="mobile-nav-close" data-mobile-nav-close aria-label="Tutup menu">×</button>
    </div>
    <div class="mobile-nav-user"><span>{{ strtoupper(substr(auth()->user()->name,0,1)) }}</span><div><b>{{ auth()->user()->name }}</b><small>{{ auth()->user()->role==='OWNER'?'Owner / Admin':'Penjaga Kos' }}</small></div></div>
    <nav class="mobile-nav-content" aria-label="Navigasi utama">
        <a href="{{ route('dashboard') }}" class="mobile-nav-home {{ $activeTab==='dashboard'?'active':'' }}"><span class="ico">⌂</span><span><b>Ringkasan</b><small>Dashboard operasional kost</small></span></a>
        @foreach($navGroups as $groupLabel=>$items)
            @php($visibleItems=collect($items)->reject(fn($item,$key)=>in_array($key,$ownerOnlyTabs,true)&&!auth()->user()->isOwner()))
            @if($visibleItems->isNotEmpty())
            <details class="mobile-nav-group" @if($visibleItems->keys()->contains($activeTab)) open @endif>
                <summary><span>{{ $groupLabel }}</span><i>⌄</i></summary>
                <div>@foreach($visibleItems as $key=>$item)<a href="{{ route('dashboard',['tab'=>$key]) }}" class="{{ $activeTab===$key?'active':'' }}"><span class="ico">{{ $item[0] }}</span><span>{{ $item[1] }}</span>@if($activeTab===$key)<i>Aktif</i>@endif</a>@endforeach</div>
            </details>
            @endif
        @endforeach
        @if(auth()->user()->isOwner())
        <details class="mobile-nav-group" @if(in_array($activeTab,['finance','imports'],true)) open @endif>
            <summary><span>Data & Laporan</span><i>⌄</i></summary>
            <div><a href="{{ route('finance') }}" class="{{ $activeTab==='finance'?'active':'' }}"><span class="ico">Rp</span><span>Keuangan</span>@if($activeTab==='finance')<i>Aktif</i>@endif</a><a href="{{ route('imports.index') }}" class="{{ $activeTab==='imports'?'active':'' }}"><span class="ico">⇧</span><span>Import Data</span>@if($activeTab==='imports')<i>Aktif</i>@endif</a></div>
        </details>
        @endif
    </nav>
    <div class="mobile-nav-foot">
        <a href="{{ route('home') }}" target="_blank" rel="noopener noreferrer"><span>↗</span>Lihat Website</a>
        <form method="post" action="{{ route('logout') }}">@csrf<button><span>⇥</span>Keluar</button></form>
    </div>
</aside>
