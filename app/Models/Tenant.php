<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    protected $fillable = ['room_id', 'name', 'phone', 'identity_number', 'move_in', 'move_out', 'next_due', 'active'];

    protected $casts = ['move_in'=>'date', 'move_out'=>'date', 'next_due'=>'date', 'active'=>'boolean'];

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function whatsappPhone(): ?string
    {
        $phone = preg_replace('/\D+/', '', $this->phone);

        if (str_starts_with($phone, '0')) {
            $phone = '62'.substr($phone, 1);
        } elseif (str_starts_with($phone, '8')) {
            $phone = '62'.$phone;
        }

        return strlen($phone) >= 10 && strlen($phone) <= 15 ? $phone : null;
    }

    public function whatsappUrl(string $template): ?string
    {
        if (! $phone = $this->whatsappPhone()) {
            return null;
        }

        $days = (int) today()->diffInDays($this->next_due, false);
        $status = match (true) {
            $days < 0 => 'sudah lewat '.abs($days).' hari',
            $days === 0 => 'jatuh tempo hari ini',
            default => 'akan jatuh tempo dalam '.$days.' hari',
        };

        $message = strtr($template, [
            '{nama}' => $this->name,
            '{kamar}' => $this->room->number,
            '{kategori}' => $this->room->category->name,
            '{jatuh_tempo}' => $this->next_due->translatedFormat('d F Y'),
            '{nominal}' => 'Rp '.number_format((float) $this->room->category->monthly_price, 0, ',', '.'),
            '{status}' => $status,
        ]);

        return 'https://wa.me/'.$phone.'?text='.rawurlencode($message);
    }
}
