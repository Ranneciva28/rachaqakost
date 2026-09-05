<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantDataForm extends Model
{
    protected $fillable = [
        'tenant_id', 'status', 'full_name', 'phone', 'identity_number', 'email',
        'birth_place', 'birth_date', 'gender', 'occupation', 'employer_or_school',
        'identity_address', 'domicile_address', 'emergency_name',
        'emergency_relationship', 'emergency_phone', 'vehicle_type', 'vehicle_plate',
        'additional_notes', 'submitted_at', 'validated_by', 'validated_at',
        'revision_opened_at', 'responses',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'submitted_at' => 'datetime',
        'validated_at' => 'datetime',
        'revision_opened_at' => 'datetime',
        'responses' => 'array',
    ];

    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function validator() { return $this->belongsTo(User::class, 'validated_by'); }
    public function uploads() { return $this->hasMany(MediaFile::class); }

    public function response(string $key, mixed $fallback = ''): mixed
    {
        return data_get($this->responses ?? [], $key, $fallback);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'VALID' => 'Formulir Valid',
            'REVISION' => 'Revisi Formulir',
            default => 'Menunggu Approval Draft',
        };
    }

    public function statusBadge(): string
    {
        return match ($this->status) {
            'VALID' => '',
            'REVISION' => 'red',
            default => 'orange',
        };
    }
}
