@if($paginator->total() > 0)
@php($last=$paginator->lastPage())
@php($current=min(max(1,$paginator->currentPage()),$last))
@php($start=max(1,$current-2))
@php($end=min($last,$current+2))
<div class="ledger-pagination-wrap">
    <p>Menampilkan <b>{{ number_format($paginator->firstItem() ?? 0,0,',','.') }}–{{ number_format($paginator->lastItem() ?? 0,0,',','.') }}</b> dari <b>{{ number_format($paginator->total(),0,',','.') }}</b> data</p>
    @if($paginator->hasPages())
    <nav class="ledger-pagination" aria-label="Navigasi halaman">
        @if($current===1)<span class="disabled">←</span>@else<a href="{{ $paginator->url($current-1) }}" rel="prev">←</a>@endif
        @if($current>3)<a href="{{ $paginator->url(1) }}">1</a>@if($current>4)<span class="ellipsis">…</span>@endif @endif
        @foreach(range($start,$end) as $page)<a href="{{ $paginator->url($page) }}" class="{{ $page===$current?'active':'' }}" @if($page===$current)aria-current="page"@endif>{{ $page }}</a>@endforeach
        @if($current<$last-2)@if($current<$last-3)<span class="ellipsis">…</span>@endif<a href="{{ $paginator->url($last) }}">{{ $last }}</a>@endif
        @if($current<$last)<a href="{{ $paginator->url($current+1) }}" rel="next">→</a>@else<span class="disabled">→</span>@endif
    </nav>
    @endif
</div>
@endif
