<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantFormField extends Model
{
    protected $fillable = ['tenant_form_section_id','key','label','type','placeholder','help_text','required','options','position','active'];
    protected $casts = ['required'=>'boolean','active'=>'boolean','options'=>'array'];
    public function section() { return $this->belongsTo(TenantFormSection::class, 'tenant_form_section_id'); }
}
