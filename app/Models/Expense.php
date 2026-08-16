<?php
namespace App\Models;use Illuminate\Database\Eloquent\Model;class Expense extends Model{protected $fillable=['title','category','amount','spent_at','notes','recorded_by'];protected $casts=['spent_at'=>'date','amount'=>'decimal:2'];public function recorder(){return $this->belongsTo(User::class,'recorded_by');}}
