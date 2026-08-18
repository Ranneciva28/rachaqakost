<?php

namespace App\Http\Controllers;

use App\Models\{Expense, ExpenseCategory, ImportBatch, ImportRow, Payment, Room, Tenant};
use App\Services\{ImageLedgerExtractor, LedgerImportService};
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ImportController extends Controller
{
    public function index(Request $request)
    {
        $this->ownerOnly($request);
        return view('imports.index', [
            'batches'=>ImportBatch::with(['uploader', 'undoer'])->withCount('rows')->latest()->limit(40)->get(),
            'visionReady'=>(bool) config('services.openai.key'),
        ]);
    }

    public function show(Request $request, ImportBatch $batch, LedgerImportService $imports)
    {
        $this->ownerOnly($request);
        $batch->load(['rows.tenant.room', 'uploader', 'undoer']);
        return view('imports.review', [
            'batch'=>$batch,
            'tenants'=>Tenant::with('room')->where('active', false)->orderBy('name')->get(),
            'rooms'=>Room::with('category')->orderBy('number')->get(),
            'expenseCategories'=>ExpenseCategory::orderBy('name')->get(),
            'roomGroups'=>$this->roomGroups($batch, $imports),
        ]);
    }

    public function mapRooms(Request $request, ImportBatch $batch, LedgerImportService $imports)
    {
        $this->ownerOnly($request);
        abort_unless($batch->status === 'DRAFT', 422, 'Pemetaan kamar hanya dapat diubah saat batch masih draft.');
        $data=$request->validate([
            'room_map'=>['required','array'],
            'room_map.*'=>['nullable','exists:rooms,id'],
        ]);
        $groups=collect($this->roomGroups($batch, $imports))->keyBy('key');

        foreach($data['room_map'] as $key=>$roomId){
            $group=$groups->get($key);
            if(!$group||!filled($roomId))continue;
            ImportRow::where('import_batch_id',$batch->id)
                ->whereIn('id',$group['row_ids'])
                ->update(['room_id'=>(int)$roomId]);
        }
        $imports->refreshBatch($batch);

        return redirect()->route('imports.show',$batch)->with('success','Pemetaan kamar diterapkan ke seluruh baris dengan nomor kamar yang sama.');
    }

    public function uploadImages(Request $request, ImageLedgerExtractor $extractor, LedgerImportService $imports)
    {
        $this->ownerOnly($request);
        $data = $request->validate([
            'images'=>['required','array','min:1','max:4'],
            'images.*'=>['required','file','max:12288','mimes:jpg,jpeg,png,webp,heic,heif'],
            'default_year'=>['required','integer','min:2000','max:2100'],
            'ledger_kind'=>['required', Rule::in(['MIXED','PAYMENT','EXPENSE','TENANT'])],
        ]);
        $files = $request->file('images');
        $batch = ImportBatch::create([
            'source_type'=>'IMAGE',
            'original_names'=>collect($files)->map->getClientOriginalName()->values()->all(),
            'status'=>'DRAFT',
            'uploaded_by'=>$request->user()->id,
        ]);

        try {
            $rows = $extractor->extract($files, (int) $data['default_year'], $data['ledger_kind']);
            $imports->createRows($batch, $rows);
            if ($batch->total_rows === 0) throw new \RuntimeException('Tidak ada baris transaksi yang terbaca dari foto.');
            return redirect()->route('imports.show', $batch)->with('success', $batch->total_rows.' baris berhasil dibaca. Periksa sebelum import.');
        } catch (\Throwable $e) {
            $batch->update(['status'=>'FAILED', 'error_message'=>Str::limit($e->getMessage(), 1000)]);
            return redirect()->route('imports.index')->withErrors(['images'=>'Foto belum dapat diproses: '.$e->getMessage()]);
        }
    }

    public function uploadCsv(Request $request, LedgerImportService $imports)
    {
        $this->ownerOnly($request);
        $request->validate(['csv'=>['required','file','max:10240','mimes:csv,txt']]);
        $file = $request->file('csv');
        $batch = ImportBatch::create([
            'source_type'=>'CSV',
            'original_names'=>[$file->getClientOriginalName()],
            'status'=>'DRAFT',
            'uploaded_by'=>$request->user()->id,
        ]);

        try {
            $rows = $this->readCsv($file->getRealPath());
            $imports->createRows($batch, $rows);
            if ($batch->total_rows === 0) throw new \RuntimeException('File CSV tidak memiliki baris data.');
            return redirect()->route('imports.show', $batch)->with('success', $batch->total_rows.' baris CSV dimuat. Periksa sebelum import.');
        } catch (\Throwable $e) {
            $batch->update(['status'=>'FAILED', 'error_message'=>Str::limit($e->getMessage(), 1000)]);
            return redirect()->route('imports.index')->withErrors(['csv'=>'CSV belum dapat diproses: '.$e->getMessage()]);
        }
    }

    public function update(Request $request, ImportBatch $batch, LedgerImportService $imports)
    {
        $this->ownerOnly($request);
        abort_unless($batch->status === 'DRAFT', 422, 'Batch yang sudah selesai tidak dapat diubah.');
        $data = $request->validate([
            'rows'=>['required','array'],
            'rows.*.selected'=>['nullable','boolean'],
            'rows.*.transaction_type'=>['required',Rule::in(['PAYMENT','EXPENSE','TENANT'])],
            'rows.*.tenant_id'=>['nullable','exists:tenants,id'],
            'rows.*.room_id'=>['nullable','exists:rooms,id'],
            'rows.*.tenant_name'=>['nullable','string','max:120'],
            'rows.*.tenant_phone'=>['nullable','string','max:30'],
            'rows.*.tenant_identity_number'=>['nullable','string','max:40'],
            'rows.*.tenant_move_in'=>['nullable','date'],
            'rows.*.tenant_move_out'=>['nullable','date'],
            'rows.*.expense_category'=>['nullable','exists:expense_categories,name'],
            'rows.*.transaction_date'=>['nullable','date'],
            'rows.*.amount'=>['nullable','string','max:30'],
            'rows.*.billing_cycle'=>['nullable',Rule::in(['DAILY','WEEKLY','MONTHLY'])],
            'rows.*.period_count'=>['nullable','integer','min:1','max:365'],
            'rows.*.period_start'=>['nullable','date'],
            'rows.*.method'=>['nullable',Rule::in(['Transfer','Cash','QRIS'])],
            'rows.*.title'=>['nullable','string','max:150'],
            'rows.*.notes'=>['nullable','string','max:2000'],
        ]);

        foreach ($batch->rows as $row) {
            $input = $data['rows'][$row->id] ?? null;
            if (! is_array($input)) continue;
            $amount = preg_replace('/\D+/', '', (string) ($input['amount'] ?? ''));
            $row->update([
                'selected'=>(bool) ($input['selected'] ?? false),
                'transaction_type'=>in_array($input['transaction_type'] ?? null, ['PAYMENT','EXPENSE','TENANT'], true) ? $input['transaction_type'] : 'PAYMENT',
                'tenant_id'=>filled($input['tenant_id'] ?? null) ? (int) $input['tenant_id'] : null,
                'room_id'=>filled($input['room_id'] ?? null) ? (int) $input['room_id'] : null,
                'tenant_name'=>filled($input['tenant_name'] ?? null) ? Str::limit(trim($input['tenant_name']), 120, '') : null,
                'tenant_phone'=>filled($input['tenant_phone'] ?? null) ? Str::limit(trim($input['tenant_phone']), 30, '') : null,
                'tenant_identity_number'=>filled($input['tenant_identity_number'] ?? null) ? Str::limit(trim($input['tenant_identity_number']), 40, '') : null,
                'tenant_move_in'=>filled($input['tenant_move_in'] ?? null) ? $input['tenant_move_in'] : null,
                'tenant_move_out'=>filled($input['tenant_move_out'] ?? null) ? $input['tenant_move_out'] : null,
                'expense_category'=>filled($input['expense_category'] ?? null) ? $input['expense_category'] : null,
                'transaction_date'=>filled($input['transaction_date'] ?? null) ? $input['transaction_date'] : null,
                'amount'=>$amount !== '' ? $amount : null,
                'billing_cycle'=>in_array($input['billing_cycle'] ?? null, ['DAILY','WEEKLY','MONTHLY'], true) ? $input['billing_cycle'] : null,
                'period_count'=>max(1, min(365, (int) ($input['period_count'] ?? 1))),
                'period_start'=>filled($input['period_start'] ?? null) ? $input['period_start'] : null,
                'method'=>in_array($input['method'] ?? null, ['Transfer','Cash','QRIS'], true) ? $input['method'] : null,
                'title'=>filled($input['title'] ?? null) ? Str::limit(trim($input['title']), 150, '') : null,
                'notes'=>filled($input['notes'] ?? null) ? Str::limit(trim($input['notes']), 2000, '') : null,
            ]);
            $imports->validateRow($row);
        }
        $imports->refreshBatch($batch);

        return redirect()->route('imports.show', $batch)->with('success', 'Koreksi draft disimpan dan divalidasi ulang.');
    }

    public function commit(Request $request, ImportBatch $batch, LedgerImportService $imports)
    {
        $this->ownerOnly($request);
        abort_unless($batch->status === 'DRAFT', 422, 'Batch sudah pernah diproses.');
        $imports->refreshBatch($batch);
        $selected = $batch->rows()->where('selected', true)->get();
        if ($selected->isEmpty()) return back()->withErrors(['batch'=>'Pilih minimal satu baris untuk di-import.']);
        if ($selected->contains(fn (ImportRow $row) => ! empty($row->validation_errors))) {
            return back()->withErrors(['batch'=>'Masih ada baris terpilih yang belum valid. Koreksi atau nonaktifkan baris tersebut.']);
        }

        DB::transaction(function () use ($batch, $selected, $request, $imports) {
            $locked = ImportBatch::whereKey($batch->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'DRAFT', 422, 'Batch sudah pernah diproses.');
            $imported = 0;
            $resolveTenant = function (ImportRow $row) use ($locked, $imports) {
                if ($row->tenant_id) {
                    $tenant=Tenant::findOrFail($row->tenant_id);
                    abort_if($tenant->active,422,'Import historis tidak boleh menggunakan penghuni aktif.');
                    return $tenant;
                }
                if ($existing = $imports->findHistoricalTenant($row)) return $existing;

                return Tenant::create([
                    'room_id'=>$row->room_id,
                    'name'=>$row->tenant_name,
                    'phone'=>$row->tenant_phone ?: '-',
                    'identity_number'=>$row->tenant_identity_number,
                    'move_in'=>$row->tenant_move_in,
                    'move_out'=>$row->tenant_move_out,
                    'next_due'=>$row->tenant_move_out ?: $row->period_start ?: $row->transaction_date ?: $row->tenant_move_in,
                    'billing_cycle'=>$row->billing_cycle ?: 'MONTHLY',
                    'active'=>false,
                    'import_batch_id'=>$locked->id,
                ]);
            };
            foreach ($selected as $row) {
                if ($row->transaction_type === 'PAYMENT') {
                    $tenant = $resolveTenant($row);
                    [$period, $coverageEnd] = $imports->periodDetails(Carbon::parse($row->period_start), $row->billing_cycle, $row->period_count);
                    $payment = Payment::create([
                        'tenant_id'=>$tenant->id,
                        'amount'=>$row->amount,
                        'paid_at'=>$row->transaction_date,
                        'period'=>$period,
                        'billing_cycle'=>$row->billing_cycle,
                        'period_count'=>$row->period_count,
                        'method'=>$row->method,
                        'recorded_by'=>$request->user()->id,
                        'is_historical'=>true,
                        'coverage_start'=>$row->period_start,
                        'coverage_end'=>$coverageEnd,
                        'import_batch_id'=>$locked->id,
                    ]);
                    $row->update([
                        'tenant_id'=>$tenant->id,
                        'imported_payment_id'=>$payment->id,
                        'imported_tenant_id'=>$tenant->import_batch_id === $locked->id ? $tenant->id : null,
                    ]);
                } elseif ($row->transaction_type === 'EXPENSE') {
                    $expense = Expense::create([
                        'title'=>$row->title,
                        'category'=>$row->expense_category,
                        'amount'=>$row->amount,
                        'spent_at'=>$row->transaction_date,
                        'notes'=>$row->notes,
                        'recorded_by'=>$request->user()->id,
                        'import_batch_id'=>$locked->id,
                    ]);
                    $row->update(['imported_expense_id'=>$expense->id]);
                } else {
                    $tenant = $resolveTenant($row);
                    $row->update([
                        'tenant_id'=>$tenant->id,
                        'imported_tenant_id'=>$tenant->import_batch_id === $locked->id ? $tenant->id : null,
                    ]);
                }
                $imported++;
            }
            $locked->update(['status'=>'COMPLETED', 'imported_rows'=>$imported, 'committed_at'=>now()]);
        });

        return redirect()->route('imports.show', $batch)->with('success', $selected->count().' data historis berhasil masuk tanpa mengubah jatuh tempo penghuni aktif.');
    }

    public function undo(Request $request, ImportBatch $batch, LedgerImportService $imports)
    {
        $this->ownerOnly($request);
        abort_unless($batch->status === 'COMPLETED', 422, 'Hanya batch import yang sudah di-commit yang dapat di-undo.');

        $deleted = DB::transaction(function () use ($batch, $request) {
            $locked = ImportBatch::whereKey($batch->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'COMPLETED', 422, 'Batch ini sudah di-undo atau belum selesai.');

            $paymentCount = Payment::where('import_batch_id', $locked->id)->count();
            $expenseCount = Expense::where('import_batch_id', $locked->id)->count();
            $tenantCount = Tenant::where('import_batch_id', $locked->id)->count();
            $tenantIds = Tenant::where('import_batch_id', $locked->id)->pluck('id');
            $usedOutsideBatch = Payment::whereIn('tenant_id', $tenantIds)
                ->where(function ($query) use ($locked) {
                    $query->whereNull('import_batch_id')->orWhere('import_batch_id', '!=', $locked->id);
                })->exists();
            if ($usedOutsideBatch) {
                throw ValidationException::withMessages(['batch'=>'Riwayat penghuni dari batch ini sudah dipakai pembayaran lain. Undo pembayaran atau batch terkait lebih dulu.']);
            }

            ImportRow::where('import_batch_id', $locked->id)->whereNotNull('imported_tenant_id')->update(['tenant_id'=>null]);
            Payment::where('import_batch_id', $locked->id)->delete();
            Expense::where('import_batch_id', $locked->id)->delete();
            Tenant::where('import_batch_id', $locked->id)->delete();
            ImportRow::where('import_batch_id', $locked->id)->update([
                'imported_payment_id'=>null,
                'imported_expense_id'=>null,
                'imported_tenant_id'=>null,
            ]);

            $locked->update([
                'status'=>'DRAFT',
                'imported_rows'=>0,
                'undo_count'=>$locked->undo_count + 1,
                'last_undone_at'=>now(),
                'last_undone_by'=>$request->user()->id,
            ]);

            return $paymentCount + $expenseCount + $tenantCount;
        });

        $imports->refreshBatch($batch->fresh());

        return redirect()->route('imports.show', $batch)->with('success', $deleted.' transaksi hasil import dibatalkan. Batch kembali menjadi draft dan dapat dikoreksi atau di-commit ulang.');
    }

    public function destroy(Request $request, ImportBatch $batch)
    {
        $this->ownerOnly($request);
        abort_if($batch->status === 'COMPLETED', 422, 'Batch selesai disimpan sebagai jejak audit dan tidak dapat dihapus.');
        $batch->delete();
        return redirect()->route('imports.index')->with('success', 'Draft batch dihapus.');
    }

    public function template(Request $request)
    {
        $this->ownerOnly($request);
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['transaction_type','tenant_name','room_number','phone','identity_number','move_in','move_out','transaction_date','amount','billing_cycle','period_count','period_start','method','category','title','notes']);
            fputcsv($out, ['TENANT','Siti','A.02','081298765432','3273010202020002','2025-11-01','2026-02-28','','','MONTHLY','','','','','','Riwayat penghuni lama']);
            fputcsv($out, ['PAYMENT','Budi','A.01','081234567890','3273010101010001','2026-01-05','2026-05-31','2026-05-10','1500000','MONTHLY','1','2026-05-01','Transfer','','','Pembayaran Mei']);
            fputcsv($out, ['EXPENSE','','','','','','','2026-05-12','350000','','','','','Listrik','Token listrik','Meter utama']);
            fclose($out);
        }, 'template-import-rachaqakost.csv', ['Content-Type'=>'text/csv; charset=UTF-8']);
    }

    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        if (! $handle) throw new \RuntimeException('File CSV tidak dapat dibuka.');
        $firstLine = fgets($handle) ?: '';
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
        rewind($handle);
        $headers = fgetcsv($handle, separator: $delimiter);
        if (! $headers) throw new \RuntimeException('Header CSV tidak ditemukan.');
        $headers = array_map(fn ($header) => Str::snake(trim(str_replace("\xEF\xBB\xBF", '', (string) $header))), $headers);
        $rows = [];
        while (($values = fgetcsv($handle, separator: $delimiter)) !== false) {
            if (count(array_filter($values, fn ($value) => trim((string) $value) !== '')) === 0) continue;
            $values = array_pad($values, count($headers), null);
            $rows[] = array_combine($headers, array_slice($values, 0, count($headers)));
            if (count($rows) > 1000) throw new \RuntimeException('Maksimal 1.000 baris per batch CSV.');
        }
        fclose($handle);
        return $rows;
    }

    private function ownerOnly(Request $request): void
    {
        abort_unless($request->user()->isOwner(), 403);
    }

    private function roomGroups(ImportBatch $batch, LedgerImportService $imports): array
    {
        $groups=[];
        $rooms=Room::all();
        foreach($batch->rows as $row){
            if(!in_array($row->transaction_type,['PAYMENT','TENANT'],true))continue;
            $source=$imports->sourceRoomNumber($row->raw_data??[]);
            if(!$source)continue;
            $key=$imports->roomMappingKey($source);
            if(!isset($groups[$key])){
                $matchedRoom=$row->room_id?null:$imports->matchRoomNumber($rooms,$source);
                $groups[$key]=[
                    'key'=>$key,
                    'source'=>$source,
                    'count'=>0,
                    'row_ids'=>[],
                    'room_id'=>$row->room_id ?: $matchedRoom?->id,
                    'auto_matched'=>!$row->room_id&&$matchedRoom!==null,
                ];
            }
            $groups[$key]['count']++;
            $groups[$key]['row_ids'][]=$row->id;
            if(!$groups[$key]['room_id']&&$row->room_id){
                $groups[$key]['room_id']=$row->room_id;
                $groups[$key]['auto_matched']=false;
            }
        }

        return array_values($groups);
    }
}
