<?php
namespace App\Models;use Illuminate\Database\Eloquent\Model;class RoomCategory extends Model{protected $fillable=['name','color','monthly_price'];protected $casts=['monthly_price'=>'decimal:2'];public function rooms(){return $this->hasMany(Room::class);}}
