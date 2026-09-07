<?php

namespace App\Http\Controllers;

use App\Models\{AppSetting, RoomCategory, WaitingListEntry, WaitingListField};
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class WaitingListController extends Controller
{
    public const DEFAULT_WHATSAPP_TEMPLATE = "Halo {nama}, kami dari RachaqaKost ingin menindaklanjuti minat Kakak untuk kamar kategori {kategori}, dengan preferensi lantai {lantai}. Apakah Kakak masih berminat jika kamar tersedia? Kami mencatat batas follow-up sampai {batas_followup}. Terima kasih.";

    public function show(RoomCategory $category)
    {
        $category->load(['rooms'=>fn($q)=>$q->orderBy('floor')->orderBy('number')]);
        $fields=WaitingListField::where('active',true)->orderBy('position')->orderBy('id')->get();
        $available=$category->rooms->where('status','KOSONG')->count();
        return response()->view('waiting-list.form',compact('category','fields','available'))
            ->header('X-Robots-Tag','noindex, nofollow, noarchive');
    }

    public function store(Request $request,RoomCategory $category)
    {
        if($category->rooms()->where('status','KOSONG')->exists()){
            return redirect()->route('home')->with('availability_notice','Kategori '.$category->name.' sudah memiliki kamar tersedia. Silakan hubungi Admin RachaqaKost.');
        }
        $fields=WaitingListField::where('active',true)->orderBy('position')->get();
        [$rules,$attributes]=$this->rules($fields);
        $validated=$request->validate($rules,[
            'answers.*.required'=>'Kolom :attribute wajib diisi.',
            'answers.*.regex'=>'Format :attribute belum benar.',
            'answers.*.in'=>'Pilihan :attribute tidak tersedia.',
            'answers.*.date_format'=>'Format :attribute belum benar.',
            'answers.*.after_or_equal'=>':attribute tidak boleh sebelum bulan berjalan.',
            'answers.*.max'=>'Isian :attribute terlalu panjang.',
        ],$attributes);
        $answers=$validated['answers']??[];
        $answers['room_category']=$category->name;
        $followupUntil=Carbon::createFromFormat('Y-m',(string)$answers['followup_until'])->startOfMonth();
        DB::transaction(function()use($category,$answers,$followupUntil){
            WaitingListEntry::create([
                'room_category_id'=>$category->id,'category_name'=>$category->name,
                'name'=>$answers['name'],'phone'=>$answers['phone'],
                'preferred_floor'=>(int)$answers['preferred_floor'],'followup_until'=>$followupUntil,
                'responses'=>$answers,'status'=>'NEW','submitted_at'=>now(),
            ]);
        });
        return redirect()->route('waiting-list.show',$category)->with('success','Terima kasih. Data waiting list sudah terkirim dan tim RachaqaKost akan menghubungi Anda jika kamar tersedia.');
    }

    public function followup(Request $request,WaitingListEntry $waitingList)
    {
        abort_if($waitingList->status==='ARCHIVED',422,'Data yang sudah diarsipkan tidak dapat di-follow-up.');
        $template=AppSetting::where('key','waiting_list_whatsapp_template')->value('value') ?: self::DEFAULT_WHATSAPP_TEMPLATE;
        $url=$waitingList->whatsappUrl($template);
        if(!$url)return back()->withErrors(['phone'=>'Nomor WhatsApp waiting list tidak valid.']);
        $waitingList->update(['status'=>'FOLLOWED_UP','followed_up_at'=>now(),'followed_up_by'=>$request->user()->id]);
        return redirect()->away($url);
    }

    public function archive(Request $request,WaitingListEntry $waitingList)
    {
        abort_unless($waitingList->followed_up_at,422,'Waiting list harus pernah di-follow-up sebelum diarsipkan.');
        $waitingList->update(['status'=>'ARCHIVED','archived_at'=>now(),'archived_by'=>$request->user()->id]);
        return back()->with('success','Waiting list '.$waitingList->name.' sudah diarsipkan.');
    }

    public function restore(Request $request,WaitingListEntry $waitingList)
    {
        $waitingList->update(['status'=>$waitingList->followed_up_at?'FOLLOWED_UP':'NEW','archived_at'=>null,'archived_by'=>null]);
        return back()->with('success','Waiting list dikembalikan ke daftar aktif.');
    }

    public function updateTemplate(Request $request)
    {
        abort_unless($request->user()->isOwner(),403);
        $data=$request->validate(['template'=>['required','string','max:1500']]);
        AppSetting::updateOrCreate(['key'=>'waiting_list_whatsapp_template'],['value'=>$data['template'],'updated_by'=>$request->user()->id]);
        return back()->with('success','Template penawaran waiting list diperbarui.');
    }

    private function rules($fields): array
    {
        $rules=[];$attributes=[];
        foreach($fields as $field){
            if($field->key==='room_category')continue;
            $name='answers.'.$field->key;$attributes[$name]=$field->label;
            $base=[$field->required?'required':'nullable'];
            $typed=match($field->type){
                'phone'=>['string','max:30','regex:/^[0-9+() .-]{9,30}$/'],
                'select'=>[Rule::in($field->options??[])],
                'month'=>['date_format:Y-m','after_or_equal:'.today()->format('Y-m')],
                'long_text'=>['string','max:2000'],
                'email'=>['email','max:150'],
                default=>['string','max:500'],
            };
            $rules[$name]=array_merge($base,$typed);
        }
        return[$rules,$attributes];
    }
}
