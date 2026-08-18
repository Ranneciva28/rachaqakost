<?php

namespace App\Services;

use App\Models\{Expense, ImportBatch, ImportRow, Payment, Tenant, User};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ImportBatchUndoService
{
    public function preview(ImportBatch $batch): array
    {
        $rowPaymentIds = $batch->rows()->whereNotNull('imported_payment_id')->pluck('imported_payment_id');
        $rowExpenseIds = $batch->rows()->whereNotNull('imported_expense_id')->pluck('imported_expense_id');
        $rowTenantIds = $batch->rows()->whereNotNull('imported_tenant_id')->pluck('imported_tenant_id');

        return [
            'payments'=>Payment::where('import_batch_id', $batch->id)->orWhereIn('id', $rowPaymentIds)->count(),
            'expenses'=>Expense::where('import_batch_id', $batch->id)->orWhereIn('id', $rowExpenseIds)->count(),
            'tenants'=>Tenant::where('import_batch_id', $batch->id)->orWhereIn('id', $rowTenantIds)->count(),
        ];
    }

    public function undo(ImportBatch $batch, User $user): array
    {
        return DB::transaction(function () use ($batch, $user) {
            $locked = ImportBatch::whereKey($batch->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'COMPLETED', 422, 'Batch ini sudah di-undo atau belum selesai.');

            $rows = ImportRow::where('import_batch_id', $locked->id)->lockForUpdate()->get();
            $paymentIds = Payment::where('import_batch_id', $locked->id)->pluck('id')
                ->merge($rows->pluck('imported_payment_id')->filter())->unique()->values();
            $expenseIds = Expense::where('import_batch_id', $locked->id)->pluck('id')
                ->merge($rows->pluck('imported_expense_id')->filter())->unique()->values();
            $tenantIds = Tenant::where('import_batch_id', $locked->id)->pluck('id')
                ->merge($rows->pluck('imported_tenant_id')->filter())->unique()->values();

            $externalPayments = Payment::whereIn('tenant_id', $tenantIds)->whereNotIn('id', $paymentIds)->exists();
            $externalRows = ImportRow::where('import_batch_id', '!=', $locked->id)
                ->where(function ($query) use ($tenantIds) {
                    $query->whereIn('tenant_id', $tenantIds)->orWhereIn('imported_tenant_id', $tenantIds);
                })->exists();
            if ($externalPayments || $externalRows) {
                throw ValidationException::withMessages([
                    'batch'=>'Penghuni hasil batch ini sudah dipakai data lain. Undo batch yang memakai penghuni tersebut lebih dulu.',
                ]);
            }

            ImportRow::where('import_batch_id', $locked->id)
                ->whereIn('tenant_id', $tenantIds)
                ->update(['tenant_id'=>null]);
            Payment::whereIn('id', $paymentIds)->delete();
            Expense::whereIn('id', $expenseIds)->delete();
            Tenant::whereIn('id', $tenantIds)->delete();
            ImportRow::where('import_batch_id', $locked->id)->update([
                'imported_payment_id'=>null,
                'imported_expense_id'=>null,
                'imported_tenant_id'=>null,
            ]);

            $locked->update([
                'status'=>'DRAFT',
                'imported_rows'=>0,
                'committed_at'=>null,
                'undo_count'=>$locked->undo_count + 1,
                'last_undone_at'=>now(),
                'last_undone_by'=>$user->id,
            ]);

            return [
                'payments'=>$paymentIds->count(),
                'expenses'=>$expenseIds->count(),
                'tenants'=>$tenantIds->count(),
                'total'=>$paymentIds->count() + $expenseIds->count() + $tenantIds->count(),
            ];
        });
    }
}
