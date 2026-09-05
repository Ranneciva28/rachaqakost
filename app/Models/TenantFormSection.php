<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantFormSection extends Model
{
    protected $fillable = ['title','description','position','active'];
    protected $casts = ['active'=>'boolean'];
    public function fields() { return $this->hasMany(TenantFormField::class)->orderBy('position')->orderBy('id'); }
}
