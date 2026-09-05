<?php

namespace App\Http\Controllers;

use App\Models\{TenantFormField, TenantFormSection};
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TenantFormBuilderController extends Controller
{
    public function storeSection(Request $request)
    {
        $this->owner($request);
        $data=$request->validate(['title'=>['required','string','max:120'],'description'=>['nullable','string','max:300'],'position'=>['required','integer','min:0','max:999']]);
        TenantFormSection::create($data+['active'=>true]);
        return back()->with('success','Section formulir ditambahkan.');
    }

    public function updateSection(Request $request,TenantFormSection $section)
    {
        $this->owner($request);
        $data=$request->validate(['title'=>['required','string','max:120'],'description'=>['nullable','string','max:300'],'position'=>['required','integer','min:0','max:999'],'active'=>['nullable','boolean']]);
        $section->update($data+['active'=>$request->boolean('active')]);
        return back()->with('success','Section formulir diperbarui.');
    }

    public function destroySection(Request $request,TenantFormSection $section)
    {
        $this->owner($request);$section->update(['active'=>false]);
        return back()->with('success','Section dinonaktifkan dan tidak lagi ditanyakan.');
    }

    public function storeField(Request $request)
    {
        $this->owner($request);$data=$this->fieldData($request);
        $base=Str::slug($data['label'],'_') ?: 'field';$key=$base;
        while(TenantFormField::where('key',$key)->exists())$key=$base.'_'.Str::lower(Str::random(5));
        TenantFormField::create($data+['key'=>$key,'active'=>true]);
        return back()->with('success','Pertanyaan formulir ditambahkan.');
    }

    public function updateField(Request $request,TenantFormField $field)
    {
        $this->owner($request);$data=$this->fieldData($request);
        $field->update($data+['active'=>$request->boolean('active')]);
        return back()->with('success','Pertanyaan formulir diperbarui.');
    }

    public function destroyField(Request $request,TenantFormField $field)
    {
        $this->owner($request);$field->update(['active'=>false]);
        return back()->with('success','Pertanyaan dinonaktifkan dan tidak lagi ditanyakan.');
    }

    private function fieldData(Request $request):array
    {
        $data=$request->validate([
            'tenant_form_section_id'=>['required','exists:tenant_form_sections,id'],
            'label'=>['required','string','max:120'],
            'type'=>['required',Rule::in(['short_text','long_text','number','date','email','phone','select','file'])],
            'placeholder'=>['nullable','string','max:180'],'help_text'=>['nullable','string','max:300'],
            'required'=>['nullable','boolean'],'options_text'=>['nullable','string','max:1500'],
            'position'=>['required','integer','min:0','max:999'],'active'=>['nullable','boolean'],
        ]);
        $options=collect(preg_split('/\r\n|\r|\n/',(string)($data['options_text']??'')))->map(fn($v)=>trim($v))->filter()->unique()->values()->all();
        if($data['type']==='select'&&count($options)<1)abort(422,'Pilihan wajib diisi untuk tipe dropdown.');
        unset($data['options_text']);$data['options']=$data['type']==='select'?$options:null;$data['required']=$request->boolean('required');
        return $data;
    }

    private function owner(Request $request):void{abort_unless($request->user()->isOwner(),403);}
}
