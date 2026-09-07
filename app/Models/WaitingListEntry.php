<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaitingListEntry extends Model
{
    protected $fillable = ['room_category_id','category_name','name','phone','preferred_floor','followup_until','responses','status','submitted_at','followed_up_at','followed_up_by','archived_at','archived_by'];
    protected $casts = ['followup_until'=>'date','responses'=>'array','submitted_at'=>'datetime','followed_up_at'=>'datetime','archived_at'=>'datetime'];

    public function category() { return $this->belongsTo(RoomCategory::class, 'room_category_id'); }
    public function followupUser() { return $this->belongsTo(User::class, 'followed_up_by'); }
    public function archiveUser() { return $this->belongsTo(User::class, 'archived_by'); }

    public function categoryLabel(): string { return $this->category?->name ?? $this->category_name; }
    public function statusLabel(): string { return match($this->status){'FOLLOWED_UP'=>'Sudah di-follow-up','ARCHIVED'=>'Diarsipkan',default=>'Belum di-follow-up'}; }
    public function statusBadge(): string { return match($this->status){'FOLLOWED_UP'=>'green','ARCHIVED'=>'gray',default=>'orange'}; }

    public function whatsappUrl(string $template): ?string
    {
        $phone=preg_replace('/\D+/','',$this->phone);
        if(str_starts_with($phone,'0'))$phone='62'.substr($phone,1);
        elseif(str_starts_with($phone,'8'))$phone='62'.$phone;
        if(strlen($phone)<10||strlen($phone)>15)return null;
        $message=strtr($template,[
            '{nama}'=>$this->name,
            '{kategori}'=>$this->categoryLabel(),
            '{lantai}'=>(string)$this->preferred_floor,
            '{batas_followup}'=>$this->followup_until->translatedFormat('F Y'),
        ]);
        return 'https://wa.me/'.$phone.'?text='.rawurlencode($message);
    }
}
