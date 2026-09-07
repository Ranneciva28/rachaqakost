@php
    $formAnswers=$form?->responses??[];
    $fallbacks=['full_name'=>$tenant->name,'phone'=>$tenant->phone,'identity_number'=>$tenant->identity_number];
    $uploads=$form?->uploads??collect();
@endphp
@foreach($sections as $section)
<section class="form-section">
    <div class="form-section-title"><span>{{ str_pad($loop->iteration,2,'0',STR_PAD_LEFT) }}</span><div><h2>{{ $section->title }}</h2>@if($section->description)<p>{{ $section->description }}</p>@endif</div></div>
    <div class="public-form-grid">
    @foreach($section->fields->sortBy('position') as $field)
        @php
            $key=$field->key;
            $stored=$formAnswers[$key]??($fallbacks[$key]??null);
            $value=old('answers.'.$key,$stored);
            $upload=$uploads->firstWhere('tenant_form_field_id',$field->id);
            $wide=in_array($field->type,['long_text','file'],true);
        @endphp
        <label class="public-field {{ $wide?'wide':'' }}">
            <span>{{ $field->label }} @if($field->required)<i>*</i>@endif</span>
            @if($field->type==='long_text')
                <textarea name="answers[{{ $key }}]" maxlength="2000" placeholder="{{ $field->placeholder }}" @required($field->required) @readonly(!$editable)>{{ $value }}</textarea>
            @elseif($field->type==='select')
                <select name="answers[{{ $key }}]" @required($field->required) @disabled(!$editable)><option value="">Pilih</option>@foreach($field->options??[] as $option)<option value="{{ $option }}" @selected($value===$option)>{{ $option }}</option>@endforeach</select>
            @elseif($field->type==='file')
                @if($editable)<input type="file" name="files[{{ $key }}]" accept="image/jpeg,image/png,image/webp,application/pdf" @required($field->required&&!$upload)><small>JPG, PNG, WEBP, atau PDF · maksimal 15 MB. Foto akan diperkecil otomatis tanpa memotong isi.</small>@endif
                @if($upload)<span class="uploaded-file">✓ {{ $upload->original_name }} · {{ number_format($upload->size/1024,0,',','.') }} KB @if(($attachmentMode??false))<a href="{{ route('tenant-form.file',[$tenant,$upload]) }}" target="_blank" rel="noopener">Lihat file</a>@endif</span>@endif
            @else
                <input type="{{ match($field->type){'email'=>'email','date'=>'date',default=>'text'} }}" name="answers[{{ $key }}]" value="{{ $value }}" maxlength="500" placeholder="{{ $field->placeholder }}" @if(in_array($field->type,['phone','number'],true)) inputmode="numeric" @endif @required($field->required) @readonly(!$editable)>
            @endif
            @if($field->help_text)<small>{{ $field->help_text }}</small>@endif
        </label>
    @endforeach
    </div>
</section>
@endforeach
