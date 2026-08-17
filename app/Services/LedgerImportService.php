<?php

namespace App\Services;

use App\Models\{Expense, ExpenseCategory, ImportBatch, ImportRow, Payment, Tenant};
use Carbon\Carbon;
use Illuminate\Support\Str;

class LedgerImportService
{
    public function createRows(ImportBatch $batch, array $sourceRows): void
    {
        $tenants = Tenant::with('room')->get();
        $categories = ExpenseCategory::all();
        $nextRow = 1;

        foreach ($sourceRows as $source) {
            $type = $this->type($source['transaction_type'] ?? $source['jenis'] ?? null);
            $tenant = $type === 'PAYMENT' ? $this->matchTenant($tenants, $source) : null;
            $category = $type === 'EXPENSE' ? $this->matchCategory($categories, $source['category'] ?? $source['kategori'] ?? null) : null;
            $cycle = $this->cycle($source['billing_cycle'] ?? $source['siklus'] ?? null) ?: ($tenant?->billing_cycle ?? 'MONTHLY');
            $date = $this->date($source['transaction_date'] ?? $source['tanggal'] ?? $source['paid_at'] ?? $source['spent_at'] ?? null);
            $periodStart = $this->date($source['period_start'] ?? $source['periode_awal'] ?? null) ?: ($type === 'PAYMENT' ? $date : null);
            $method = $this->method($source['method'] ?? $source['metode'] ?? null) ?: ($type === 'PAYMENT' ? 'Transfer' : null);
            $confidence = isset($source['confidence']) ? max(0, min(100, (float) $source['confidence'])) : null;
            $notes = trim((string) ($source['notes'] ?? $source['catatan'] ?? '')) ?: null;
            if (! empty($source['tenant_name']) && ! $tenant) {
                $notes = trim('Terbaca: '.($source['tenant_name'] ?? '').(! empty($source['room_number']) ? ' · Kamar '.$source['room_number'] : '').($notes ? ' · '.$notes : ''));
            }

            $row = ImportRow::create([
                'import_batch_id'=>$batch->id,
                'row_number'=>$nextRow++,
                'transaction_type'=>$type,
                'tenant_id'=>$tenant?->id,
                'expense_category'=>$category?->name,
                'transaction_date'=>$date,
                'amount'=>$this->amount($source['amount'] ?? $source['nominal'] ?? null),
                'billing_cycle'=>$type === 'PAYMENT' ? $cycle : null,
                'period_count'=>$type === 'PAYMENT' ? max(1, min(365, (int) ($source['period_count'] ?? $source['jumlah_periode'] ?? 1))) : 1,
                'period_start'=>$periodStart,
                'method'=>$method,
                'title'=>$type === 'EXPENSE' ? (trim((string) ($source['title'] ?? $source['keterangan'] ?? '')) ?: null) : null,
                'notes'=>$notes,
                'confidence'=>$confidence,
                'raw_data'=>$source,
            ]);
            $this->validateRow($row);
        }

        $this->refreshBatch($batch);
    }

    public function validateRow(ImportRow $row): array
    {
        $errors = [];
        if (! $row->selected) {
            $row->update(['validation_errors'=>[]]);
            return [];
        }
        if (! in_array($row->transaction_type, ['PAYMENT','EXPENSE'], true)) $errors[] = 'Jenis transaksi tidak valid.';
        if (! $row->transaction_date) $errors[] = 'Tanggal transaksi wajib diisi.';
        if (! $row->amount || (float) $row->amount <= 0) $errors[] = 'Nominal harus lebih dari nol.';

        if ($row->transaction_type === 'PAYMENT') {
            if (! $row->tenant_id || ! Tenant::whereKey($row->tenant_id)->exists()) $errors[] = 'Pilih penghuni yang sesuai.';
            if (! in_array($row->billing_cycle, ['DAILY','WEEKLY','MONTHLY'], true)) $errors[] = 'Pilih siklus pembayaran.';
            if (! $row->period_start) $errors[] = 'Periode awal wajib diisi.';
            $limit = match ($row->billing_cycle) {'DAILY'=>365, 'WEEKLY'=>52, default=>24};
            if ($row->period_count < 1 || $row->period_count > $limit) $errors[] = 'Jumlah periode melewati batas siklus.';
            if (! in_array($row->method, ['Transfer','Cash','QRIS'], true)) $errors[] = 'Pilih metode pembayaran.';
            if ($row->tenant_id && $row->transaction_date && $row->amount && $row->period_start && in_array($row->billing_cycle, ['DAILY','WEEKLY','MONTHLY'], true)) {
                $duplicate = Payment::whereNotNull('import_batch_id')->where('tenant_id', $row->tenant_id)->whereDate('paid_at', $row->transaction_date)->where('amount', $row->amount)->where('billing_cycle', $row->billing_cycle)->where('period_count', $row->period_count)->whereDate('coverage_start', $row->period_start)->exists();
                $duplicateInBatch = ImportRow::where('import_batch_id', $row->import_batch_id)->where('id', '!=', $row->id)->where('selected', true)->where('transaction_type', 'PAYMENT')->where('tenant_id', $row->tenant_id)->whereDate('transaction_date', $row->transaction_date)->where('amount', $row->amount)->where('billing_cycle', $row->billing_cycle)->where('period_count', $row->period_count)->whereDate('period_start', $row->period_start)->exists();
                if ($duplicate || $duplicateInBatch) $errors[] = 'Transaksi identik terdeteksi; nonaktifkan salah satu agar tidak dobel.';
            }
        } else {
            if (! $row->expense_category || ! ExpenseCategory::where('name', $row->expense_category)->exists()) $errors[] = 'Pilih kategori pengeluaran.';
            if (! trim((string) $row->title)) $errors[] = 'Nama pengeluaran wajib diisi.';
            if ($row->transaction_date && $row->amount && $row->expense_category && trim((string) $row->title)) {
                $duplicate = Expense::whereNotNull('import_batch_id')->whereDate('spent_at', $row->transaction_date)->where('amount', $row->amount)->where('category', $row->expense_category)->where('title', $row->title)->exists();
                $duplicateInBatch = ImportRow::where('import_batch_id', $row->import_batch_id)->where('id', '!=', $row->id)->where('selected', true)->where('transaction_type', 'EXPENSE')->whereDate('transaction_date', $row->transaction_date)->where('amount', $row->amount)->where('expense_category', $row->expense_category)->where('title', $row->title)->exists();
                if ($duplicate || $duplicateInBatch) $errors[] = 'Transaksi identik terdeteksi; nonaktifkan salah satu agar tidak dobel.';
            }
        }

        $row->update(['validation_errors'=>$errors]);
        return $errors;
    }

    public function refreshBatch(ImportBatch $batch): void
    {
        $rows = $batch->rows()->get();
        foreach ($rows as $row) $this->validateRow($row);
        $selected = $rows->where('selected', true);
        $batch->update([
            'total_rows'=>$rows->count(),
            'valid_rows'=>$selected->filter(fn (ImportRow $row) => empty($row->validation_errors))->count(),
        ]);
    }

    public function periodDetails(Carbon $start, string $cycle, int $count): array
    {
        if ($cycle === 'DAILY') {
            $end = $start->copy()->addDays($count - 1);
            $label = $count === 1 ? $start->translatedFormat('d F Y') : $start->translatedFormat('d M Y').' – '.$end->translatedFormat('d M Y');
            return [$label, $end];
        }
        if ($cycle === 'WEEKLY') {
            $end = $start->copy()->addWeeks($count)->subDay();
            return [$start->translatedFormat('d M Y').' – '.$end->translatedFormat('d M Y'), $end];
        }
        $lastStart = $start->copy()->addMonthsNoOverflow($count - 1);
        $end = $start->copy()->addMonthsNoOverflow($count)->subDay();
        $label = $count === 1 ? $start->translatedFormat('F Y') : $start->translatedFormat('F Y').' – '.$lastStart->translatedFormat('F Y');
        return [$label, $end];
    }

    private function type(mixed $value): string
    {
        $value = Str::upper(trim((string) $value));
        return in_array($value, ['EXPENSE','PENGELUARAN','KELUAR','DEBIT'], true) ? 'EXPENSE' : 'PAYMENT';
    }

    private function cycle(mixed $value): ?string
    {
        $value = Str::upper(trim((string) $value));
        return match (true) {
            in_array($value, ['DAILY','HARIAN','HARI'], true) => 'DAILY',
            in_array($value, ['WEEKLY','MINGGUAN','MINGGU'], true) => 'WEEKLY',
            in_array($value, ['MONTHLY','BULANAN','BULAN'], true) => 'MONTHLY',
            default => null,
        };
    }

    private function method(mixed $value): ?string
    {
        $value = Str::upper(trim((string) $value));
        return match (true) {
            str_contains($value, 'CASH') || str_contains($value, 'TUNAI') => 'Cash',
            str_contains($value, 'QRIS') => 'QRIS',
            str_contains($value, 'TRANSFER') || str_contains($value, 'TF') || str_contains($value, 'BANK') => 'Transfer',
            default => null,
        };
    }

    private function date(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') return null;
        foreach (['Y-m-d','d/m/Y','d-m-Y','d.m.Y','j/n/Y','j-n-Y'] as $format) {
            try {
                $date = Carbon::createFromFormat($format, $value);
                if ($date && $date->format($format) === $value) return $date->toDateString();
            } catch (\Throwable) {}
        }
        try { return Carbon::parse($value)->toDateString(); } catch (\Throwable) { return null; }
    }

    private function amount(mixed $value): ?float
    {
        if (is_numeric($value)) return (float) $value > 0 ? (float) $value : null;
        $digits = preg_replace('/\D+/', '', (string) $value);
        return $digits !== '' && (float) $digits > 0 ? (float) $digits : null;
    }

    private function matchTenant($tenants, array $source): ?Tenant
    {
        $name = $this->normalize($source['tenant_name'] ?? $source['penghuni'] ?? $source['nama'] ?? null);
        $room = $this->normalize($source['room_number'] ?? $source['kamar'] ?? null);
        if (! $name && ! $room) return null;

        $matches = $tenants->filter(function (Tenant $tenant) use ($name, $room) {
            $nameMatches = ! $name || $this->normalize($tenant->name) === $name;
            $roomMatches = ! $room || $this->normalize($tenant->room?->number) === $room;
            return $nameMatches && $roomMatches;
        });
        return $matches->count() === 1 ? $matches->first() : null;
    }

    private function matchCategory($categories, mixed $value): ?ExpenseCategory
    {
        $name = $this->normalize($value);
        if (! $name) return null;
        return $categories->first(fn (ExpenseCategory $category) => $this->normalize($category->name) === $name);
    }

    private function normalize(mixed $value): string
    {
        return preg_replace('/[^a-z0-9]+/', '', Str::lower(Str::ascii(trim((string) $value))));
    }
}
