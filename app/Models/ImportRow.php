<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportRow extends Model
{
    protected $fillable = ['import_batch_id', 'row_number', 'selected', 'transaction_type', 'tenant_id', 'expense_category', 'transaction_date', 'amount', 'billing_cycle', 'period_count', 'period_start', 'method', 'title', 'notes', 'confidence', 'validation_errors', 'raw_data', 'imported_payment_id', 'imported_expense_id'];
    protected $casts = ['selected'=>'boolean', 'transaction_date'=>'date', 'period_start'=>'date', 'amount'=>'decimal:2', 'period_count'=>'integer', 'confidence'=>'decimal:2', 'validation_errors'=>'array', 'raw_data'=>'array'];

    public function batch() { return $this->belongsTo(ImportBatch::class, 'import_batch_id'); }
    public function tenant() { return $this->belongsTo(Tenant::class); }
}
