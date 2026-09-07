<!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow,noarchive"><title>Waiting List {{ $category->name }} — RachaqaKost</title>@include('partials.branding-head')<link rel="stylesheet" href="{{ asset('assets/waiting-list.css') }}?v=20260907"></head>
<body><main class="waiting-shell">
    <a class="waiting-brand" href="{{ route('home') }}"><span>@if($brandLogo)<img src="{{ route('media.public',$brandLogo) }}" alt="Logo RachaqaKost">@else K @endif</span><div><b>RachaqaKost</b><small>Form Waiting List</small></div></a>
    <section class="waiting-card">
        <div class="waiting-intro"><span class="eyebrow">WAITING LIST KAMAR</span><h1>{{ $category->name }}</h1><p>Daftarkan minat Anda. Tim RachaqaKost akan menghubungi Anda jika kamar pada kategori ini kembali tersedia.</p></div>
        @if(session('success'))
            <div class="waiting-success"><strong>✓ Data berhasil dikirim</strong><p>{{ session('success') }}</p><a href="{{ route('home') }}#pilihan-kamar">Kembali melihat kamar</a></div>
        @elseif($available>0)
            <div class="waiting-available"><strong>Kamar sudah tersedia</strong><p>Saat ini terdapat {{ $available }} kamar kosong pada kategori {{ $category->name }}. Silakan kembali ke homepage untuk menghubungi Admin.</p><a href="{{ route('home') }}#pilihan-kamar">Lihat ketersediaan kamar</a></div>
        @else
            @if($errors->any())<div class="waiting-errors"><strong>Periksa kembali data berikut:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            <form method="post" action="{{ route('waiting-list.store',$category) }}">@csrf
                <div class="waiting-grid">
                @foreach($fields as $field)
                    @php($value=old('answers.'.$field->key))
                    <label class="waiting-field {{ $field->type==='long_text'?'wide':'' }}"><span>{{ $field->label }} @if($field->required)<i>*</i>@endif</span>
                        @if($field->key==='room_category')
                            <input value="{{ $category->name }}" readonly aria-readonly="true" class="locked-field">
                        @elseif($field->type==='select')
                            <select name="answers[{{ $field->key }}]" @required($field->required)><option value="">Pilih</option>@foreach($field->options??[] as $option)<option value="{{ $option }}" @selected((string)$value===(string)$option)>{{ $field->key==='preferred_floor'?'Lantai '.$option:$option }}</option>@endforeach</select>
                        @elseif($field->type==='long_text')
                            <textarea name="answers[{{ $field->key }}]" maxlength="2000" placeholder="{{ $field->placeholder }}" @required($field->required)>{{ $value }}</textarea>
                        @else
                            <input type="{{ match($field->type){'email'=>'email','month'=>'month',default=>'text'} }}" name="answers[{{ $field->key }}]" value="{{ $value }}" placeholder="{{ $field->placeholder }}" @if($field->type==='phone') inputmode="tel" @endif @if($field->type==='month') min="{{ today()->format('Y-m') }}" @endif @required($field->required)>
                        @endif
                        @if($field->help_text)<small>{{ $field->help_text }}</small>@endif
                    </label>
                @endforeach
                </div>
                <label class="waiting-consent"><input type="checkbox" required><span>Saya bersedia dihubungi oleh tim RachaqaKost melalui WhatsApp terkait ketersediaan kamar.</span></label>
                <button class="waiting-submit">Kirim Minat Waiting List</button>
            </form>
        @endif
    </section>
    <footer>Data hanya digunakan untuk tindak lanjut ketersediaan kamar RachaqaKost.</footer>
</main></body></html>
