@if($brandFavicon)
<link rel="icon" type="image/png" sizes="96x96" href="{{ route('media.public',$brandFavicon) }}">
<link rel="apple-touch-icon" href="{{ route('media.public',$brandLogo ?: $brandFavicon) }}">
@endif
<link rel="stylesheet" href="{{ asset('assets/branding.css') }}?v=20260907">
@if($brandLogo)
<style>
    .brandmark,.public-brand>span{background-image:url('{{ route('media.public',$brandLogo) }}')!important;background-repeat:no-repeat!important;background-position:center!important;background-size:contain!important;font-size:0!important}
    .brandmark img,.site-brand img{display:block;width:100%;height:100%;object-fit:contain}
</style>
@endif
