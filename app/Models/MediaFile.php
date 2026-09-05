<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MediaFile extends Model
{
    protected $fillable = ['kind','room_category_id','tenant_data_form_id','tenant_form_field_id','original_name','mime_type','size','contents','position'];
    protected $hidden = ['contents'];
    public function scopeMetadata($query) { return $query->select(['id','kind','room_category_id','tenant_data_form_id','tenant_form_field_id','original_name','mime_type','size','position','created_at','updated_at']); }
    public function category() { return $this->belongsTo(RoomCategory::class, 'room_category_id'); }
    public function tenantForm() { return $this->belongsTo(TenantDataForm::class); }
    public function field() { return $this->belongsTo(TenantFormField::class, 'tenant_form_field_id'); }
}
