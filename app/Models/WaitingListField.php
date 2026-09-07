<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaitingListField extends Model
{
    protected $fillable = ['key','label','type','placeholder','help_text','required','options','position','active','is_system'];
    protected $casts = ['required'=>'boolean','active'=>'boolean','is_system'=>'boolean','options'=>'array'];
}
