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
<details class="mobile-menu">
    <summary><b>☰</b><span>{{ $titles[$activeTab] }}</span><i>Menu</i></summary>
    <div class="mobile-menu-panel"><a href="{{ route('dashboard') }}">⌂ Ringkasan</a>@foreach($navGroups as $items)@foreach($items as $key=>$item)@if(!in_array($key,$ownerOnlyTabs,true)||auth()->user()->isOwner())<a href="{{ route('dashboard',['tab'=>$key]) }}" class="{{ $activeTab===$key?'active':'' }}">{{ $item[0] }} {{ $item[1] }}</a>@endif @endforeach @endforeach @if(auth()->user()->isOwner())<a href="{{ route('finance') }}">Rp Keuangan</a><a href="{{ route('imports.index') }}">⇧ Import Data</a><a href="{{ route('home') }}" target="_blank">↗ Lihat Website</a>@endif</div>
</details>
