<?php

namespace App\Http\Controllers;

use App\Models\{AppSetting, MediaFile, Tenant, TenantDataForm, TenantFormField, TenantFormSection};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TenantDataFormController extends Controller
{
    public function publicShow(string $token)
    {
        $tenant=Tenant::with(['room.category','tenantForm.uploads'=>fn($q)=>$q->metadata()])->where('form_token',$token)->firstOrFail();
        return response()->view('tenant-forms.public',['tenant'=>$tenant,'form'=>$tenant->tenantForm,'sections'=>$this->sections()])
            ->header('X-Robots-Tag','noindex, nofollow, noarchive')->header('Referrer-Policy','no-referrer');
    }

    public function publicSubmit(Request $request,string $token)
    {
        $tenant=Tenant::with(['tenantForm.uploads'=>fn($q)=>$q->metadata()])->where('form_token',$token)->firstOrFail();
        abort_if($tenant->tenantForm&&$tenant->tenantForm->status!=='REVISION',423,'Formulir sudah dikunci. Hubungi pengelola RachaqaKost untuk membuka revisi.');
        $fields=TenantFormField::where('active',true)->whereHas('section',fn($q)=>$q->where('active',true))->orderBy('position')->get();
        [$rules,$attributes]=$this->rules($fields,$tenant->tenantForm);
        $validated=$request->validate($rules,[],$attributes);
        $answers=[];
        foreach($fields->where('type','!=','file') as $field)$answers[$field->key]=data_get($validated,'answers.'.$field->key);

        DB::transaction(function()use($tenant,$fields,$answers,$validated){
            $answers=array_replace($tenant->tenantForm?->responses??[],$answers);
            $form=$tenant->tenantForm()->updateOrCreate([], $this->legacyValues($tenant,$answers)+[
                'responses'=>$answers,'status'=>'PENDING_APPROVAL','submitted_at'=>now(),
                'validated_by'=>null,'validated_at'=>null,'revision_opened_at'=>null,
            ]);
            foreach($fields->where('type','file') as $field){
                $file=data_get($validated,'files.'.$field->key);if(!$file)continue;
                $form->uploads()->where('tenant_form_field_id',$field->id)->delete();
                $stream=fopen($file->getRealPath(),'rb');
                throw_if($stream===false,\RuntimeException::class,'File upload tidak dapat dibaca.');
                try{
                    $form->uploads()->create(['kind'=>'KTP','tenant_form_field_id'=>$field->id,'original_name'=>$file->getClientOriginalName(),
                        'mime_type'=>$file->getMimeType()?:'application/octet-stream','size'=>$file->getSize(),'contents'=>$stream,'position'=>0]);
                }finally{
                    fclose($stream);
                }
            }
        });
        return redirect()->route('tenant-form.public',$tenant->form_token)->with('success','Formulir berhasil dikirim dan sedang menunggu validasi pengelola.');
    }

    public function attachment(Tenant $tenant)
    {
        $form=$tenant->tenantForm()->with(['validator','uploads'=>fn($q)=>$q->metadata()->with('field')])->firstOrFail();
        return view('tenant-forms.attachment',['tenant'=>$tenant,'form'=>$form,'sections'=>$this->sections(true,$form)]);
    }

    public function downloadUpload(Tenant $tenant,MediaFile $media)
    {
        abort_unless((int)$media->tenant_data_form_id===(int)$tenant->tenantForm?->id&&$media->kind==='KTP',404);
        $filename=preg_replace('/[\r\n"]+/','_',basename($media->original_name))?:'dokumen';
        $contents=$media->contents;
        return response()->stream(function()use($contents){
            if(is_resource($contents)){
                $metadata=stream_get_meta_data($contents);
                if($metadata['seekable']??false)rewind($contents);
                fpassthru($contents);
                return;
            }
            echo $contents;
        },200,['Content-Type'=>$media->mime_type,'Content-Length'=>(string)$media->size,
            'Content-Disposition'=>'inline; filename="'.$filename.'"','X-Content-Type-Options'=>'nosniff','Cache-Control'=>'private, no-store']);
    }

    public function validateForm(Request $request,Tenant $tenant)
    {
        $this->ownerOnly($request);$form=$tenant->tenantForm()->firstOrFail();
        abort_unless($form->status==='PENDING_APPROVAL',422,'Hanya draft yang menunggu approval yang dapat divalidasi.');
        DB::transaction(function()use($tenant,$form,$request){
            $form->update(['status'=>'VALID','validated_by'=>$request->user()->id,'validated_at'=>now(),'revision_opened_at'=>null]);
            $answers=$form->responses??[];$sync=[];
            foreach(['full_name'=>'name','phone'=>'phone','identity_number'=>'identity_number'] as $key=>$column)if(filled($answers[$key]??null))$sync[$column]=$answers[$key];
            if($sync)$tenant->update($sync);
        });
        return back()->with('success','Formulir sudah valid dan data utama penghuni disinkronkan.');
    }

    public function requestRevision(Request $request,Tenant $tenant)
    {
        $this->ownerOnly($request);$form=$tenant->tenantForm()->firstOrFail();
        abort_if($form->status==='REVISION',422,'Formulir sudah berada dalam mode revisi.');
        $form->update(['status'=>'REVISION','validated_by'=>null,'validated_at'=>null,'revision_opened_at'=>now()]);
        return back()->with('success','Mode revisi dibuka. Penghuni dapat mengubah formulir melalui link yang sama.');
    }

    public function updateTemplate(Request $request)
    {
        $this->ownerOnly($request);$data=$request->validate(['template'=>['required','string','max:1500']]);
        AppSetting::updateOrCreate(['key'=>'tenant_form_whatsapp_template'],['value'=>$data['template'],'updated_by'=>$request->user()->id]);
        return back()->with('success','Template WhatsApp formulir diperbarui.');
    }

    private function sections(bool $includeAnsweredInactive=false,?TenantDataForm $form=null)
    {
        return TenantFormSection::with(['fields'=>function($query)use($includeAnsweredInactive,$form){
            if(!$includeAnsweredInactive)$query->where('active',true);
            elseif($form){$keys=array_keys($form->responses??[]);$query->where(fn($q)=>$q->where('active',true)->orWhereIn('key',$keys)->orWhere('type','file'));}
        }])->when(!$includeAnsweredInactive,fn($q)=>$q->where('active',true))->orderBy('position')->orderBy('id')->get()->filter(fn($section)=>$section->fields->isNotEmpty());
    }

    private function rules($fields,?TenantDataForm $form):array
    {
        $rules=[];$attributes=[];
        foreach($fields as $field){$name=($field->type==='file'?'files.':'answers.').$field->key;$attributes[$name]=$field->label;
            $required=$field->required;
            if($field->type==='file'){$hasFile=$form?->uploads?->contains('tenant_form_field_id',$field->id);$rules[$name]=array_filter([$required&&!$hasFile?'required':'nullable','file','mimes:jpg,jpeg,png,webp,pdf','max:5120']);continue;}
            $base=[$required?'required':'nullable'];
            $typeRules=match($field->type){
                'email'=>['email','max:150'],'phone'=>['string','max:30','regex:/^[0-9+() .-]{9,30}$/'],
                'number'=>['string','max:40','regex:/^[0-9.,-]+$/'],'date'=>['date'],
                'select'=>[Rule::in($field->options??[])],'long_text'=>['string','max:2000'],default=>['string','max:500'],};
            if($field->key==='identity_number')$typeRules=['string','regex:/^[0-9]{12,20}$/'];
            if($field->key==='birth_date')$typeRules=['date','before:today'];
            $rules[$name]=array_merge($base,$typeRules);
        }
        return[$rules,$attributes];
    }

    private function legacyValues(Tenant $tenant,array $answers):array
    {
        $existing=$tenant->tenantForm;
        $map=['full_name','phone','identity_number','email','birth_place','birth_date','gender','occupation','employer_or_school','identity_address','domicile_address','emergency_name','emergency_relationship','emergency_phone','vehicle_type','vehicle_plate','additional_notes'];
        $values=[];foreach($map as $key)$values[$key]=$answers[$key]??($existing?$existing->{$key}:null);
        $values['full_name']??=$tenant->name;$values['phone']??=$tenant->phone;$values['identity_number']??=$tenant->identity_number;
        return $values;
    }
    private function ownerOnly(Request $request):void{abort_unless($request->user()->isOwner(),403);}
}
