<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessSimulation extends Model
{
    protected $fillable = [
        'name',
        'daily_occupancy',
        'daily_rate',
        'ota_commission',
        'projection_days',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'daily_occupancy' => 'decimal:2',
            'daily_rate' => 'decimal:2',
            'ota_commission' => 'decimal:2',
            'projection_days' => 'integer',
        ];
    }

    public function costs()
    {
        return $this->hasMany(BusinessSimulationCost::class)->orderBy('type')->orderBy('name');
    }
}
