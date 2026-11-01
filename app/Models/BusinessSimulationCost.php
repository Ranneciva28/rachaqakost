<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessSimulationCost extends Model
{
    public const TYPES = ['OPERATIONAL', 'INVESTMENT'];
    public const SCENARIOS = ['FULL_MONTHLY', 'HYBRID', 'BOTH'];

    protected $fillable = ['business_simulation_id', 'type', 'applies_to', 'name', 'amount', 'notes'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function simulation()
    {
        return $this->belongsTo(BusinessSimulation::class, 'business_simulation_id');
    }
}
