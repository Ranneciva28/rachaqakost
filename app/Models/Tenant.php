<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Tenant extends Model
{
    protected $fillable = ['room_id', 'name', 'phone', 'identity_number', 'move_in', 'move_out', 'next_due', 'billing_cycle', 'active', 'import_batch_id'];

    protected $casts = ['move_in'=>'date', 'move_out'=>'date', 'next_due'=>'date', 'active'=>'boolean'];

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function importBatch()
    {
        return $this->belongsTo(ImportBatch::class);
    }

    public function tenantForm()
    {
        return $this->hasOne(TenantDataForm::class);
    }

    protected static function booted(): void
    {
        static::creating(function (Tenant $tenant) {
            if ($tenant->form_token) return;
            $phone = preg_replace('/\D+/', '', (string) $tenant->phone);
            $identity = preg_replace('/\D+/', '', (string) $tenant->identity_number);
            $prefix = str_pad(substr($phone, -4), 4, '0', STR_PAD_LEFT).str_pad(substr($identity, -3), 3, '0', STR_PAD_LEFT);
            do $token = $prefix.'-'.Str::lower(Str::random(24));
            while (static::where('form_token', $token)->exists());
            $tenant->form_token = $token;
        });
    }

    public function billingRate(): float
    {
        return (float) match ($this->billing_cycle) {
            'DAILY' => $this->room->category->daily_price,
            'WEEKLY' => $this->room->category->weekly_price,
            default => $this->room->category->monthly_price,
        };
    }

    public function billingCycleLabel(): string
    {
        return match ($this->billing_cycle) {
            'DAILY' => 'Harian',
            'WEEKLY' => 'Mingguan',
            default => 'Bulanan',
        };
    }

    public function daysUntilDue(): int
    {
        return (int) today()->startOfDay()->diffInDays($this->next_due->copy()->startOfDay(), false);
    }

    public function isOverdue(): bool
    {
        return $this->daysUntilDue() < 0;
    }

    public function isDueToday(): bool
    {
        return $this->daysUntilDue() === 0;
    }

    public function overdueDays(): int
    {
        return max(0, -$this->daysUntilDue());
    }

    public function dueStatusLabel(): string
    {
        return match (true) {
            $this->isOverdue() => 'Terlambat H+'.$this->overdueDays(),
            $this->isDueToday() => 'Jatuh Tempo Hari Ini',
            default => $this->next_due->translatedFormat('d M'),
        };
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

        $days = $this->daysUntilDue();
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
            '{nominal}' => 'Rp '.number_format($this->billingRate(), 0, ',', '.'),
            '{siklus}' => strtolower($this->billingCycleLabel()),
            '{status}' => $status,
        ]);

        return 'https://wa.me/'.$phone.'?text='.rawurlencode($message);
    }

    public function formStatusLabel(): string
    {
        return $this->tenantForm?->statusLabel() ?? 'Formulir Belum Diisi';
    }

    public function formStatusBadge(): string
    {
        return $this->tenantForm?->statusBadge() ?? 'gray';
    }

    public function formWhatsappUrl(string $template): ?string
    {
        if (! $phone = $this->whatsappPhone()) return null;
        $message = strtr($template, [
            '{nama}' => $this->name,
            '{kamar}' => $this->room->number,
            '{link_formulir}' => route('tenant-form.public', $this->form_token),
            '{status_formulir}' => $this->formStatusLabel(),
        ]);
        return 'https://wa.me/'.$phone.'?text='.rawurlencode($message);
    }
}
