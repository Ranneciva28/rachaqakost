<?php

namespace App\Http\Controllers;

use App\Models\WaitingListField;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class WaitingListFormBuilderController extends Controller
{
    private const TYPES=['short_text','long_text','email','phone','select','month'];

    public function store(Request $request)
    {
        $this->owner($request);$data=$this->fieldData($request);
        $base=Str::slug($data['label'],'_')?:'pertanyaan';$key=$base;
        while(WaitingListField::where('key',$key)->exists())$key=$base.'_'.Str::lower(Str::random(5));
        WaitingListField::create($data+['key'=>$key,'active'=>true,'is_system'=>false]);
        return back()->with('success','Pertanyaan waiting list ditambahkan.');
    }

    public function update(Request $request,WaitingListField $field)
    {
        $this->owner($request);
        if($field->is_system){
            $data=$request->validate(['label'=>['required','string','max:120'],'placeholder'=>['nullable','string','max:180'],'help_text'=>['nullable','string','max:300'],'position'=>['required','integer','min:0','max:999']]);
            $field->update($data+['required'=>true,'active'=>true]);
        }else{
            $field->update($this->fieldData($request)+['active'=>$request->boolean('active')]);
        }
        return back()->with('success','Pertanyaan waiting list diperbarui.');
    }

    public function destroy(Request $request,WaitingListField $field)
    {
        $this->owner($request);abort_if($field->is_system,422,'Pertanyaan bawaan tidak dapat dinonaktifkan.');
        $field->update(['active'=>false]);
        return back()->with('success','Pertanyaan waiting list dinonaktifkan.');
    }

    private function fieldData(Request $request): array
    {
        $data=$request->validate([
            'label'=>['required','string','max:120'],'type'=>['required',Rule::in(self::TYPES)],
            'placeholder'=>['nullable','string','max:180'],'help_text'=>['nullable','string','max:300'],
            'required'=>['nullable','boolean'],'options_text'=>['nullable','string','max:1500'],
            'position'=>['required','integer','min:0','max:999'],'active'=>['nullable','boolean'],
        ]);
        $options=collect(preg_split('/\r\n|\r|\n/',(string)($data['options_text']??'')))->map(fn($v)=>trim($v))->filter()->unique()->values()->all();
        if($data['type']==='select'&&!$options)abort(422,'Pilihan wajib diisi untuk tipe dropdown.');
        unset($data['options_text']);$data['options']=$data['type']==='select'?$options:null;$data['required']=$request->boolean('required');
        return $data;
    }

    private function owner(Request $request): void { abort_unless($request->user()->isOwner(),403); }
}
