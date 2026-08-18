<?php

namespace App\Services;

use App\Models\{Expense, ExpenseCategory, ImportBatch, ImportRow, Payment, Room, Tenant};
use Carbon\Carbon;
use Illuminate\Support\Str;

class LedgerImportService
{
    public function createRows(ImportBatch $batch, array $sourceRows): void
    {
        // Penghuni milik batch lain tidak dipakai ulang agar setiap batch tetap
        // dapat di-undo secara mandiri tanpa menghapus data batch lain.
        $tenants = Tenant::with('room')->where('active', false)->whereNull('import_batch_id')->get();
        $rooms = Room::all();
        $categories = ExpenseCategory::all();
        $nextRow = 1;

        foreach ($sourceRows as $source) {
            $type = $this->type($source['transaction_type'] ?? $source['jenis'] ?? null);
            $tenant = $type === 'PAYMENT' ? $this->matchTenant($tenants, $source) : null;
            $room = $tenant?->room ?: $this->matchRoomNumber($rooms, $this->sourceRoomNumber($source));
            $category = $type === 'EXPENSE' ? $this->matchCategory($categories, $source['category'] ?? $source['kategori'] ?? null) : null;
            $cycle = $this->cycle($source['billing_cycle'] ?? $source['siklus'] ?? null) ?: ($tenant?->billing_cycle ?? 'MONTHLY');
            $date = $this->date($source['transaction_date'] ?? $source['tanggal'] ?? $source['paid_at'] ?? $source['spent_at'] ?? null);
            $periodStart = $this->date($source['period_start'] ?? $source['periode_awal'] ?? null) ?: ($type === 'PAYMENT' ? $date : null);
            $method = $this->method($source['method'] ?? $source['metode'] ?? null) ?: ($type === 'PAYMENT' ? 'Transfer' : null);
            $confidence = isset($source['confidence']) ? max(0, min(100, (float) $source['confidence'])) : null;
            $notes = trim((string) ($source['notes'] ?? $source['catatan'] ?? '')) ?: null;
            if ($type === 'PAYMENT' && ! empty($source['tenant_name']) && ! $tenant && ! $room) {
                $notes = trim('Terbaca: '.($source['tenant_name'] ?? '').(! empty($source['room_number']) ? ' · Kamar '.$source['room_number'] : '').($notes ? ' · '.$notes : ''));
            }

            $row = ImportRow::create([
                'import_batch_id'=>$batch->id,
                'row_number'=>$nextRow++,
                'transaction_type'=>$type,
                'tenant_id'=>$tenant?->id,
                'room_id'=>$room?->id,
                'tenant_name'=>trim((string) ($source['tenant_name'] ?? $source['penghuni'] ?? $source['nama'] ?? '')) ?: null,
                'tenant_phone'=>trim((string) ($source['phone'] ?? $source['tenant_phone'] ?? $source['no_hp'] ?? '')) ?: null,
                'tenant_identity_number'=>trim((string) ($source['identity_number'] ?? $source['no_identitas'] ?? '')) ?: null,
                'tenant_move_in'=>$this->date($source['move_in'] ?? $source['tanggal_masuk'] ?? null),
                'tenant_move_out'=>$this->date($source['move_out'] ?? $source['tanggal_keluar'] ?? null),
                'expense_category'=>$category?->name,
                'transaction_date'=>$date,
                'amount'=>$this->amount($source['amount'] ?? $source['nominal'] ?? null),
                'billing_cycle'=>in_array($type, ['PAYMENT','TENANT'], true) ? $cycle : null,
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
        if (! in_array($row->transaction_type, ['PAYMENT','EXPENSE','TENANT'], true)) $errors[] = 'Jenis transaksi tidak valid.';

        if ($row->transaction_type === 'PAYMENT') {
            if (! $row->transaction_date) $errors[] = 'Tanggal transaksi wajib diisi.';
            if (! $row->amount || (float) $row->amount <= 0) $errors[] = 'Nominal harus lebih dari nol.';
            $linkedTenant=$row->tenant_id?Tenant::find($row->tenant_id):null;
            if($linkedTenant?->active)$errors[] = 'Import historis tidak boleh ditautkan ke penghuni aktif.';
            if($linkedTenant?->import_batch_id && $linkedTenant->import_batch_id !== $row->import_batch_id)$errors[] = 'Penghuni dari batch lain tidak dapat dipakai agar undo tetap mandiri.';
            if (! $linkedTenant) {
                $errors = array_merge($errors, $this->tenantHistoryErrors($row, false, false));
            }
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
        } elseif ($row->transaction_type === 'EXPENSE') {
            if (! $row->transaction_date) $errors[] = 'Tanggal transaksi wajib diisi.';
            if (! $row->amount || (float) $row->amount <= 0) $errors[] = 'Nominal harus lebih dari nol.';
            if (! $row->expense_category || ! ExpenseCategory::where('name', $row->expense_category)->exists()) $errors[] = 'Pilih kategori pengeluaran.';
            if (! trim((string) $row->title)) $errors[] = 'Nama pengeluaran wajib diisi.';
            if ($row->transaction_date && $row->amount && $row->expense_category && trim((string) $row->title)) {
                $duplicate = Expense::whereNotNull('import_batch_id')->whereDate('spent_at', $row->transaction_date)->where('amount', $row->amount)->where('category', $row->expense_category)->where('title', $row->title)->exists();
                $duplicateInBatch = ImportRow::where('import_batch_id', $row->import_batch_id)->where('id', '!=', $row->id)->where('selected', true)->where('transaction_type', 'EXPENSE')->whereDate('transaction_date', $row->transaction_date)->where('amount', $row->amount)->where('expense_category', $row->expense_category)->where('title', $row->title)->exists();
                if ($duplicate || $duplicateInBatch) $errors[] = 'Transaksi identik terdeteksi; nonaktifkan salah satu agar tidak dobel.';
            }
        } else {
            $errors = array_merge($errors, $this->tenantHistoryErrors($row, true));
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

    public function findHistoricalTenant(ImportRow $row): ?Tenant
    {
        if (! $row->room_id || ! $row->tenant_name || ! $row->tenant_move_in) return null;

        $query=Tenant::where('room_id', $row->room_id)
            ->where('active', false)
            ->where(function ($query) use ($row) {
                $query->whereNull('import_batch_id')->orWhere('import_batch_id', $row->import_batch_id);
            })
            ->whereDate('move_in', $row->tenant_move_in);
        $row->tenant_move_out
            ?$query->whereDate('move_out',$row->tenant_move_out)
            :$query->whereNull('move_out');

        return $query
            ->get()
            ->first(fn (Tenant $tenant) => $this->normalize($tenant->name) === $this->normalize($row->tenant_name));
    }

    private function type(mixed $value): string
    {
        $value = Str::upper(trim((string) $value));
        if (in_array($value, ['TENANT','TENANT_HISTORY','PENGHUNI','RIWAYAT_PENGHUNI','CHECKOUT'], true)) return 'TENANT';
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
        $room = $this->normalizeRoomNumber($this->sourceRoomNumber($source));
        $moveIn = $this->date($source['move_in'] ?? $source['tanggal_masuk'] ?? null);
        $moveOut = $this->date($source['move_out'] ?? $source['tanggal_keluar'] ?? null);
        if (! $name && ! $room) return null;

        $matches = $tenants->filter(function (Tenant $tenant) use ($name, $room, $moveIn, $moveOut) {
            $nameMatches = ! $name || $this->normalize($tenant->name) === $name;
            $roomMatches = ! $room || $this->normalizeRoomNumber($tenant->room?->number) === $room;
            $moveInMatches = ! $moveIn || $tenant->move_in?->toDateString() === $moveIn;
            $moveOutMatches = ! $moveOut || $tenant->move_out?->toDateString() === $moveOut;
            return $nameMatches && $roomMatches && $moveInMatches && $moveOutMatches;
        });
        return $matches->count() === 1 ? $matches->first() : null;
    }

    private function matchCategory($categories, mixed $value): ?ExpenseCategory
    {
        $name = $this->normalize($value);
        if (! $name) return null;
        return $categories->first(fn (ExpenseCategory $category) => $this->normalize($category->name) === $name);
    }

    public function matchRoomNumber($rooms, mixed $value): ?Room
    {
        $number = $this->normalizeRoomNumber($value);
        if (! $number) return null;
        return $rooms->first(fn (Room $room) => $this->normalizeRoomNumber($room->number) === $number);
    }

    public function sourceRoomNumber(array $source): ?string
    {
        foreach (['room_number','nomor_kamar','no_kamar','kamar','room','unit','nomor_unit','no_unit'] as $key) {
            $value=trim((string)($source[$key]??''));
            if($value!=='')return $value;
        }

        return null;
    }

    public function roomMappingKey(mixed $value): string
    {
        return sha1($this->normalizeRoomNumber($value));
    }

    private function normalizeRoomNumber(mixed $value): string
    {
        $value=Str::lower(Str::ascii(trim((string)$value)));
        $value=preg_replace('/^(kamar|room|unit|nomor|no)[\s._#:-]*/','',$value);
        preg_match_all('/[a-z]+|\d+/',$value,$matches);
        $tokens=array_map(
            fn(string $token)=>ctype_digit($token)?(string)((int)$token):$token,
            $matches[0],
        );

        return implode('|',$tokens);
    }

    private function tenantHistoryErrors(ImportRow $row, bool $rejectExisting = false, bool $requireMoveOut = true): array
    {
        $errors = [];
        if (! trim((string) $row->tenant_name)) $errors[] = 'Nama penghuni wajib diisi.';
        if (! $row->room_id || ! Room::whereKey($row->room_id)->exists()) $errors[] = 'Pilih kamar yang sesuai.';
        if (! $row->tenant_move_in) $errors[] = 'Tanggal masuk wajib diisi.';
        if ($requireMoveOut && ! $row->tenant_move_out) $errors[] = 'Tanggal keluar wajib diisi untuk riwayat checkout.';
        if ($row->tenant_move_in && $row->tenant_move_out && $row->tenant_move_out->lt($row->tenant_move_in)) $errors[] = 'Tanggal keluar tidak boleh sebelum tanggal masuk.';
        if (! in_array($row->billing_cycle, ['DAILY','WEEKLY','MONTHLY'], true)) $errors[] = 'Pilih siklus sewa.';

        if (empty($errors) && $rejectExisting && $this->findHistoricalTenant($row)) {
            $errors[] = 'Riwayat penghuni yang sama sudah ada di database.';
        }
        if (empty($errors) && $rejectExisting) {
            $duplicateInBatch = ImportRow::where('import_batch_id', $row->import_batch_id)
                ->where('id', '!=', $row->id)
                ->where('selected', true)
                ->whereIn('transaction_type', ['TENANT','PAYMENT'])
                ->whereNull('tenant_id')
                ->where('room_id', $row->room_id)
                ->whereDate('tenant_move_in', $row->tenant_move_in)
                ->whereDate('tenant_move_out', $row->tenant_move_out)
                ->get()
                ->contains(fn (ImportRow $candidate) => $this->normalize($candidate->tenant_name) === $this->normalize($row->tenant_name));
            if ($duplicateInBatch) $errors[] = 'Riwayat penghuni yang sama muncul lebih dari sekali dalam batch.';
        }

        return $errors;
    }

    private function normalize(mixed $value): string
    {
        return preg_replace('/[^a-z0-9]+/', '', Str::lower(Str::ascii(trim((string) $value))));
    }
}
