<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportBatch extends Model
{
    protected $fillable = ['source_type', 'original_names', 'status', 'total_rows', 'valid_rows', 'imported_rows', 'error_message', 'uploaded_by', 'committed_at', 'undo_count', 'last_undone_at', 'last_undone_by'];
    protected $casts = ['original_names'=>'array', 'committed_at'=>'datetime', 'undo_count'=>'integer', 'last_undone_at'=>'datetime'];

    public function rows() { return $this->hasMany(ImportRow::class); }
    public function uploader() { return $this->belongsTo(User::class, 'uploaded_by'); }
    public function undoer() { return $this->belongsTo(User::class, 'last_undone_by'); }
}
