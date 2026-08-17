<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpenseCategory extends Model
{
    protected $fillable = ['name', 'color', 'cost_type', 'cost_behavior', 'is_system'];

    protected $casts = ['is_system'=>'boolean'];
}
