<?php

namespace App\Http\Controllers;

use App\Models\{AppSetting, MediaFile, RoomCategory};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WebsiteController extends Controller
{
    public const SETTING_KEYS=['site_hero_title','site_hero_subtitle','site_about','site_address','site_facilities','site_admin_whatsapp','site_whatsapp_message'];

    public static function defaults():array{return['site_hero_title'=>'Hunian nyaman untuk keseharian yang lebih tenang.','site_hero_subtitle'=>'RachaqaKost menghadirkan kamar yang nyaman, terawat, dan mudah dijangkau.','site_about'=>'Temukan pilihan kamar sesuai kebutuhan harian, mingguan, maupun bulanan dengan pengelolaan yang responsif.','site_address'=>'Alamat RachaqaKost dapat diatur melalui panel Owner.','site_facilities'=>"Kamar nyaman\nLingkungan terawat\nPengelola responsif",'site_admin_whatsapp'=>'6282213630344','site_whatsapp_message'=>'Halo Admin RachaqaKost, saya tertarik dengan kategori {kategori}. Apakah masih tersedia?'];}

    public function updateContent(Request $request)
    {
        $this->owner($request);$data=$request->validate([
            'site_hero_title'=>['required','string','max:120'],'site_hero_subtitle'=>['required','string','max:250'],
            'site_about'=>['required','string','max:2000'],'site_address'=>['required','string','max:500'],
            'site_facilities'=>['required','string','max:2000'],'site_admin_whatsapp'=>['required','string','max:30','regex:/^[0-9+() .-]{9,30}$/'],
            'site_whatsapp_message'=>['required','string','max:1000'],
        ]);
        DB::transaction(function()use($data,$request){foreach($data as $key=>$value)AppSetting::updateOrCreate(['key'=>$key],['value'=>$value,'updated_by'=>$request->user()->id]);});
        return back()->with('success','Konten homepage dan nomor WhatsApp diperbarui.');
    }

    public function uploadHero(Request $request)
    {
        $this->owner($request);$data=$request->validate(['photo'=>['required','image','mimes:jpg,jpeg,png,webp','max:5120']]);$file=$data['photo'];
        DB::transaction(function()use($file){MediaFile::where('kind','HERO')->delete();MediaFile::create($this->fileData($file)+['kind'=>'HERO']);});
        return back()->with('success','Foto utama homepage diperbarui.');
    }

    public function uploadCategoryPhotos(Request $request,RoomCategory $category)
    {
        $this->owner($request);$data=$request->validate(['photos'=>['required','array','max:8'],'photos.*'=>['required','image','mimes:jpg,jpeg,png,webp','max:5120']]);
        $existing=$category->photos()->count();abort_if($existing+count($data['photos'])>8,422,'Maksimal 8 foto untuk setiap kategori kamar.');
        DB::transaction(function()use($category,$data,$existing){foreach($data['photos'] as $offset=>$file)MediaFile::create($this->fileData($file)+['kind'=>'CATEGORY','room_category_id'=>$category->id,'position'=>$existing+$offset+1]);});
        return back()->with('success','Foto kategori '.$category->name.' ditambahkan.');
    }

    public function deleteMedia(Request $request,MediaFile $media)
    {
        $this->owner($request);abort_unless(in_array($media->kind,['HERO','CATEGORY'],true),404);$media->delete();
        return back()->with('success','Foto dihapus dari homepage.');
    }

    private function fileData($file):array{return['original_name'=>$file->getClientOriginalName(),'mime_type'=>$file->getMimeType()?:'application/octet-stream','size'=>$file->getSize(),'contents'=>file_get_contents($file->getRealPath()),'position'=>0];}
    private function owner(Request $request):void{abort_unless($request->user()->isOwner(),403);}
}
