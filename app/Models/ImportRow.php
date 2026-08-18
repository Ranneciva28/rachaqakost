<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportRow extends Model
{
    protected $fillable = ['import_batch_id', 'row_number', 'selected', 'transaction_type', 'tenant_id', 'room_id', 'tenant_name', 'tenant_phone', 'tenant_identity_number', 'tenant_move_in', 'tenant_move_out', 'expense_category', 'transaction_date', 'amount', 'billing_cycle', 'period_count', 'period_start', 'method', 'title', 'notes', 'confidence', 'validation_errors', 'raw_data', 'imported_payment_id', 'imported_expense_id', 'imported_tenant_id'];
    protected $casts = ['selected'=>'boolean', 'transaction_date'=>'date', 'period_start'=>'date', 'tenant_move_in'=>'date', 'tenant_move_out'=>'date', 'amount'=>'decimal:2', 'period_count'=>'integer', 'confidence'=>'decimal:2', 'validation_errors'=>'array', 'raw_data'=>'array'];

    public function batch() { return $this->belongsTo(ImportBatch::class, 'import_batch_id'); }
    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function room() { return $this->belongsTo(Room::class); }
}
