<?php

namespace App\Http\Controllers;

use App\Models\{AppSetting, Expense, ExpenseCategory, Maintenance, Payment, Room, RoomCategory, Tenant, User};
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Services\LedgerTableService;

class KostController extends Controller
{
    private const DEFAULT_WHATSAPP_TEMPLATE = "Halo {nama}, kami dari RachaqaKost ingin mengingatkan pembayaran sewa kamar #{kamar} sebesar {nominal} yang {status}, tepatnya pada {jatuh_tempo}. Mohon konfirmasinya ya. Terima kasih.";

    public function index(Request $request, LedgerTableService $ledger)
    {
        [$reportFrom, $reportTo] = $this->reportRange($request);
        [$cashflowFrom, $cashflowTo] = $this->cashflowRange($request);
        $rooms = Room::with(['category', 'activeTenant'])->withCount(['tenants', 'maintenances', 'importRows'])->orderBy('floor')->orderBy('number')->get();
        $tenants = Tenant::with('room.category')->where('active', true)->orderBy('next_due')->get();
        [$cashflow, $cashflowGranularity] = $this->cashflowSeries($cashflowFrom, $cashflowTo);
        $incomeQuery = Payment::whereBetween('paid_at', [$reportFrom->toDateString(), $reportTo->toDateString()]);
        $expenseQuery = Expense::whereBetween('spent_at', [$reportFrom->toDateString(), $reportTo->toDateString()]);
        $income = (float) (clone $incomeQuery)->sum('amount');
        $expenseTotal = (float) (clone $expenseQuery)->sum('amount');
        $activeTab = $request->string('tab')->value() ?: 'dashboard';
        $allowed = ['dashboard','rooms','tenants','payments','expenses','maintenance','users'];
        if (!in_array($activeTab, $allowed, true) || ($activeTab === 'users' && !$request->user()->isOwner())) $activeTab = 'dashboard';
        $paymentFilters=$ledger->paymentFilters($request);
        $expenseFilters=$ledger->expenseFilters($request);
        $payments=collect();$expenses=collect();$paymentFilteredTotal=0.0;$expenseFilteredTotal=0.0;
        if($activeTab==='payments')[$payments,$paymentFilteredTotal]=$ledger->payments($paymentFilters);
        if($activeTab==='expenses')[$expenses,$expenseFilteredTotal]=$ledger->expenses($expenseFilters);

        return view('dashboard', [
            'activeTab'=>$activeTab, 'rooms'=>$rooms, 'categories'=>RoomCategory::withCount('rooms')->orderBy('name')->get(),
            'tenants'=>$tenants, 'tenantHistory'=>Tenant::with('room.category')->where('active',false)->latest('move_out')->limit(40)->get(),
            'paymentTenants'=>Tenant::with('room.category')->orderByDesc('active')->orderBy('name')->get(),
            'payments'=>$payments, 'paymentFilters'=>$paymentFilters, 'paymentFilteredTotal'=>$paymentFilteredTotal,
            'expenses'=>$expenses, 'expenseFilters'=>$expenseFilters, 'expenseFilteredTotal'=>$expenseFilteredTotal,
            'ledgerPageSizes'=>LedgerTableService::PAGE_SIZES,
            'expenseCategories'=>ExpenseCategory::orderByDesc('is_system')->orderBy('name')->get(),
            'maintenances'=>Maintenance::with(['room','recorder'])->latest('reported_at')->get(),
            'income'=>$income, 'expenseTotal'=>$expenseTotal, 'profit'=>$income-$expenseTotal,
            'incomeTransactionCount'=>(clone $incomeQuery)->count(), 'expenseTransactionCount'=>(clone $expenseQuery)->count(),
            'reportFrom'=>$reportFrom, 'reportTo'=>$reportTo,
            'cashflowFrom'=>$cashflowFrom, 'cashflowTo'=>$cashflowTo, 'cashflowGranularity'=>$cashflowGranularity,
            'dueSoon'=>$tenants->filter(fn(Tenant $t)=>$t->next_due->lte(now()->addDays(7))),
            'cashflow'=>$cashflow, 'maxCashflow'=>max(1,(float)$cashflow->flatMap(fn($p)=>[$p['income'],$p['expense']])->max()),
            'users'=>$request->user()->isOwner()?User::orderBy('name')->get():collect(),
            'whatsappTemplate'=>AppSetting::where('key','whatsapp_payment_template')->value('value') ?: self::DEFAULT_WHATSAPP_TEMPLATE,
        ]);
    }

    public function storeCategory(Request $request) { $this->ownerOnly($request); RoomCategory::create($this->categoryData($request)); return back()->with('success','Kategori berhasil ditambahkan.'); }
    public function updateCategory(Request $request, RoomCategory $category) { $this->ownerOnly($request); $category->update($this->categoryData($request,$category)); return back()->with('success','Kategori dan harga diperbarui.'); }
    public function destroyCategory(Request $request, RoomCategory $category) { $this->ownerOnly($request); if($category->rooms()->exists())return back()->withErrors(['category'=>'Kategori masih dipakai kamar. Pindahkan atau hapus kamar tersebut lebih dulu.']); $category->delete(); return back()->with('success','Kategori kamar berhasil dihapus.'); }

    public function storeRoom(Request $request)
    {
        $data=$request->validate(['number'=>['required','string','max:20','unique:rooms,number'],'room_category_id'=>['required','exists:room_categories,id'],'floor'=>['required','integer','min:1','max:99']]);
        Room::create($data+['status'=>'KOSONG']); return back()->with('success','Kamar baru ditambahkan.');
    }

    public function updateRoom(Request $request, Room $room)
    {
        $data=$request->validate(['number'=>['required','max:20',Rule::unique('rooms','number')->ignore($room)],'room_category_id'=>['required','exists:room_categories,id'],'floor'=>['required','integer','min:1','max:99'],'status'=>['required',Rule::in(['KOSONG','TERISI','MAINTENANCE'])]]);
        if($room->activeTenant()->exists()&&$data['status']==='KOSONG') return back()->withErrors(['status'=>'Check-out penghuni dulu sebelum mengosongkan kamar.']);
        $room->update($data); return back()->with('success','Kamar diperbarui.');
    }

    public function destroyRoom(Request $request, Room $room)
    {
        $this->ownerOnly($request);
        if($room->tenants()->exists())return back()->withErrors(['room'=>'Kamar memiliki data penghuni atau riwayat checkout sehingga tidak dapat dihapus.']);
        if($room->maintenances()->exists())return back()->withErrors(['room'=>'Kamar memiliki riwayat maintenance sehingga tidak dapat dihapus.']);
        if($room->importRows()->exists())return back()->withErrors(['room'=>'Kamar masih dipakai dalam batch import. Hapus draft batch terkait lebih dulu.']);
        $number=$room->number;
        $room->delete();
        return back()->with('success','Kamar #'.$number.' berhasil dihapus.');
    }

    public function tenantIn(Request $request)
    {
        $data=$request->validate(['room_id'=>['required','exists:rooms,id'],'name'=>['required','max:120'],'phone'=>['required','max:30'],'identity_number'=>['nullable','max:40'],'move_in'=>['required','date'],'next_due'=>['required','date','after_or_equal:move_in'],'billing_cycle'=>['required',Rule::in(['DAILY','WEEKLY','MONTHLY'])]]);
        $room=Room::with('category')->findOrFail($data['room_id']); abort_if($room->status!=='KOSONG'||$room->activeTenant()->exists(),422,'Kamar tidak tersedia.');
        DB::transaction(function()use($data,$room){Tenant::create($data+['active'=>true]);$room->update(['status'=>'TERISI']);});
        return back()->with('success','Penghuni check-in; kamar otomatis terisi.');
    }

    public function tenantOut(Request $request, Tenant $tenant)
    {
        abort_unless($tenant->active,422,'Penghuni sudah check-out.');
        $data=$request->validate(['move_out'=>['required','date','after_or_equal:'.$tenant->move_in->toDateString()]]);
        DB::transaction(function()use($tenant,$data){$tenant->update(['active'=>false,'move_out'=>$data['move_out']]);$tenant->room->update(['status'=>$tenant->room->maintenances()->where('status','!=','SELESAI')->exists()?'MAINTENANCE':'KOSONG']);});
        return back()->with('success','Check-out tercatat; kamar kembali tersedia.');
    }

    public function updateTenant(Request $request, Tenant $tenant)
    {
        $data=$request->validate(['room_id'=>['required','exists:rooms,id'],'name'=>['required','string','max:120'],'phone'=>['required','string','max:30'],'identity_number'=>['nullable','string','max:40'],'move_in'=>['required','date'],'next_due'=>['required','date','after_or_equal:move_in'],'billing_cycle'=>['required',Rule::in(['DAILY','WEEKLY','MONTHLY'])],'move_out'=>[Rule::requiredIf(!$tenant->active),'nullable','date','after_or_equal:move_in']]);
        $targetRoom=Room::findOrFail($data['room_id']);
        $roomChanged=(int)$tenant->room_id!==(int)$targetRoom->id;
        if($tenant->active&&$roomChanged)abort_if($targetRoom->status!=='KOSONG'||$targetRoom->activeTenant()->exists(),422,'Kamar tujuan tidak tersedia. Pilih kamar kosong.');
        if($tenant->active)$data['move_out']=null;
        DB::transaction(function()use($tenant,$targetRoom,$roomChanged,$data){$oldRoom=$tenant->room;$tenant->update($data);if($tenant->active&&$roomChanged){$oldRoom->update(['status'=>$oldRoom->maintenances()->where('status','!=','SELESAI')->exists()?'MAINTENANCE':'KOSONG']);$targetRoom->update(['status'=>'TERISI']);}});
        return back()->with('success','Data penghuni berhasil diperbarui.');
    }

    public function payment(Request $request)
    {
        $this->normalizeCurrencyFields($request, ['amount']);
        $data=$request->validate(['tenant_id'=>['required','exists:tenants,id'],'amount'=>['required','numeric','min:1'],'paid_at'=>['required','date'],'method'=>['required',Rule::in(['Transfer','Cash','QRIS'])],'periods'=>['required','integer','min:1','max:365'],'payment_mode'=>['required',Rule::in(['REGULAR','HISTORICAL'])],'billing_cycle'=>['required',Rule::in(['DAILY','WEEKLY','MONTHLY'])],'period_start'=>['nullable','date']]);
        DB::transaction(function()use($data,$request){
            $tenant=Tenant::with('room.category')->findOrFail($data['tenant_id']);
            $historical=$data['payment_mode']==='HISTORICAL';
            abort_if(!$historical&&!$tenant->active,422,'Pembayaran reguler hanya dapat dicatat untuk penghuni aktif.');
            abort_if($historical&&empty($data['period_start']),422,'Periode awal wajib diisi untuk pembayaran historis.');
            $cycle=$data['billing_cycle'];
            $limit=match($cycle){'DAILY'=>365,'WEEKLY'=>52,default=>24};
            abort_if((int)$data['periods']>$limit,422,'Jumlah periode melebihi batas untuk siklus tagihan ini.');

            // next_due represents the LAST covered/rent-valid date. The next regular
            // payment therefore starts on the following day. If this tenant has never
            // had a regular payment, move_in is the first coverage date.
            if($historical){
                $periodStart=Carbon::parse($data['period_start'])->startOfDay();
            }else{
                $hasRegularPayment=$tenant->payments()->where('is_historical',false)->exists();
                $periodStart=$hasRegularPayment
                    ?$tenant->next_due->copy()->addDay()->startOfDay()
                    :$tenant->move_in->copy()->startOfDay();
            }

            [$period,$coverageEnd]=$this->paymentPeriod($periodStart,$cycle,(int)$data['periods']);
            Payment::create(['tenant_id'=>$tenant->id,'amount'=>$data['amount'],'paid_at'=>$data['paid_at'],'period'=>$period,'billing_cycle'=>$cycle,'period_count'=>$data['periods'],'method'=>$data['method'],'recorded_by'=>$request->user()->id,'is_historical'=>$historical,'coverage_start'=>$periodStart,'coverage_end'=>$coverageEnd]);
            if(!$historical)$tenant->update(['next_due'=>$coverageEnd,'billing_cycle'=>$cycle]);
        });
        return back()->with('success',$data['payment_mode']==='HISTORICAL'?'Pembayaran historis tersimpan tanpa mengubah jatuh tempo.':'Pembayaran tersimpan; masa sewa dan jatuh tempo otomatis diperbarui.');
    }

    public function expense(Request $request){$data=$this->expenseData($request);Expense::create($data+['recorded_by'=>$request->user()->id]);return back()->with('success','Pengeluaran dicatat.');}
    public function updateExpense(Request $request,Expense $expense){$this->ownerOnly($request);$data=$this->expenseData($request);$maintenance=$expense->maintenance;if($maintenance)$data['category']='Maintenance';DB::transaction(function()use($expense,$data,$maintenance){$expense->update($data);if($maintenance)$maintenance->update(['cost'=>$data['amount'],'reported_at'=>$data['spent_at'],'completed_at'=>$data['spent_at']]);});return back()->with('success','Pengeluaran dan periode keuangan berhasil diperbarui.');}
    public function storeExpenseCategory(Request $request){$this->ownerOnly($request);ExpenseCategory::create($this->expenseCategoryData($request));return back()->with('success','Kategori pengeluaran ditambahkan.');}
    public function updateExpenseCategory(Request $request,ExpenseCategory $expenseCategory){$this->ownerOnly($request);$oldName=$expenseCategory->name;$data=$this->expenseCategoryData($request,$expenseCategory);abort_if($expenseCategory->is_system&&$oldName!==$data['name'],422,'Nama kategori sistem Maintenance tidak dapat diubah.');DB::transaction(function()use($expenseCategory,$oldName,$data){$expenseCategory->update($data);if($oldName!==$data['name'])Expense::where('category',$oldName)->update(['category'=>$data['name']]);});return back()->with('success','Kategori pengeluaran diperbarui.');}
    public function destroyExpenseCategory(Request $request,ExpenseCategory $expenseCategory){$this->ownerOnly($request);abort_if($expenseCategory->is_system,422,'Kategori sistem Maintenance tidak dapat dihapus.');$expenseCategory->delete();return back()->with('success','Kategori dihapus; histori pengeluaran tetap tersimpan.');}
    public function maintenance(Request $request)
    {
        $this->normalizeCurrencyFields($request, ['cost']);
        $data=$request->validate([
            'room_id'=>['required','exists:rooms,id'],
            'title'=>['required','max:150'],
            'cost'=>['required','numeric','min:1'],
            'maintenance_at'=>['required','date'],
            'notes'=>['nullable','max:500'],
        ]);
        $room=Room::findOrFail($data['room_id']);

        DB::transaction(function()use($data,$request,$room){
            $expense=Expense::create([
                'title'=>'Maintenance kamar #'.$room->number.': '.$data['title'],
                'category'=>'Maintenance',
                'amount'=>$data['cost'],
                'spent_at'=>$data['maintenance_at'],
                'notes'=>$data['notes'],
                'recorded_by'=>$request->user()->id,
            ]);
            Maintenance::create([
                'room_id'=>$room->id,
                'title'=>$data['title'],
                'status'=>'SELESAI',
                'cost'=>$data['cost'],
                'reported_at'=>$data['maintenance_at'],
                'completed_at'=>$data['maintenance_at'],
                'notes'=>$data['notes'],
                'recorded_by'=>$request->user()->id,
                'expense_id'=>$expense->id,
            ]);
        });

        return back()->with('success','Maintenance tercatat di riwayat kamar dan pengeluaran bulanan.');
    }
    public function updateWhatsAppTemplate(Request $request){$this->ownerOnly($request);$data=$request->validate(['template'=>['required','string','max:1500']]);AppSetting::updateOrCreate(['key'=>'whatsapp_payment_template'],['value'=>$data['template'],'updated_by'=>$request->user()->id]);return back()->with('success','Template follow-up WhatsApp diperbarui.');}

    private function ownerOnly(Request $request):void{abort_unless($request->user()->isOwner(),403);}
    private function expenseData(Request $request):array{$this->normalizeCurrencyFields($request,['amount']);return $request->validate(['title'=>['required','max:150'],'category'=>['required',Rule::exists('expense_categories','name')],'amount'=>['required','numeric','min:1'],'spent_at'=>['required','date'],'notes'=>['nullable','max:500']]);}
    private function categoryData(Request $request,?RoomCategory $category=null):array{$this->normalizeCurrencyFields($request,['daily_price','weekly_price','monthly_price']);return $request->validate(['name'=>['required','max:80',Rule::unique('room_categories','name')->ignore($category)],'daily_price'=>['required','numeric','min:0'],'weekly_price'=>['required','numeric','min:0'],'monthly_price'=>['required','numeric','min:0'],'color'=>['required','regex:/^#[0-9A-Fa-f]{6}$/']]);}
    private function expenseCategoryData(Request $request,?ExpenseCategory $category=null):array{return $request->validate(['name'=>['required','max:60',Rule::unique('expense_categories','name')->ignore($category)],'color'=>['required','regex:/^#[0-9A-Fa-f]{6}$/'],'cost_type'=>['required',Rule::in(['DIRECT','OPERATING'])],'cost_behavior'=>['required',Rule::in(['VARIABLE','FIXED'])]]);}
    private function reportRange(Request $request):array{$from=$request->string('from')->value();$to=$request->string('to')->value();$from=Carbon::hasFormat($from,'Y-m-d')?Carbon::createFromFormat('Y-m-d',$from)->startOfDay():now()->startOfMonth();$to=Carbon::hasFormat($to,'Y-m-d')?Carbon::createFromFormat('Y-m-d',$to)->endOfDay():now()->endOfDay();if($from->gt($to))[$from,$to]=[$to->copy()->startOfDay(),$from->copy()->endOfDay()];return[$from,$to];}
    private function cashflowRange(Request $request):array{$from=$request->string('cashflow_from')->value();$to=$request->string('cashflow_to')->value();$from=Carbon::hasFormat($from,'Y-m-d')?Carbon::createFromFormat('Y-m-d',$from)->startOfDay():now()->subMonths(5)->startOfMonth();$to=Carbon::hasFormat($to,'Y-m-d')?Carbon::createFromFormat('Y-m-d',$to)->endOfDay():now()->endOfDay();if($from->gt($to))[$from,$to]=[$to->copy()->startOfDay(),$from->copy()->endOfDay()];$latest=$from->copy()->addYear()->subDay()->endOfDay();if($to->gt($latest))$to=$latest;return[$from,$to];}
    private function cashflowSeries(Carbon $from,Carbon $to):array{$payments=Payment::whereBetween('paid_at',[$from->toDateString(),$to->toDateString()])->get(['amount','paid_at']);$expenses=Expense::whereBetween('spent_at',[$from->toDateString(),$to->toDateString()])->get(['amount','spent_at']);$days=$from->diffInDays($to)+1;$granularity=$days<=31?'Harian':($days<=120?'Mingguan':'Bulanan');$points=collect();$cursor=$from->copy()->startOfDay();while($cursor->lte($to)){if($granularity==='Harian'){$start=$cursor->copy();$end=$cursor->copy()->endOfDay();$label=$start->translatedFormat('d M');$cursor->addDay();}elseif($granularity==='Mingguan'){$start=$cursor->copy();$end=$cursor->copy()->addDays(6)->endOfDay()->min($to);$label=$start->translatedFormat('d M').'–'.$end->translatedFormat('d M');$cursor=$end->copy()->addDay()->startOfDay();}else{$start=$cursor->copy();$end=$cursor->copy()->endOfMonth()->min($to);$label=$start->translatedFormat('M Y');$cursor=$end->copy()->addDay()->startOfDay();}$points->push(['label'=>$label,'income'=>(float)$payments->filter(fn(Payment $payment)=>$payment->paid_at->betweenIncluded($start,$end))->sum('amount'),'expense'=>(float)$expenses->filter(fn(Expense $expense)=>$expense->spent_at->betweenIncluded($start,$end))->sum('amount')]);}return[$points,$granularity];}
    private function normalizeCurrencyFields(Request $request,array $fields):void{foreach($fields as $field){if($request->filled($field))$request->merge([$field=>preg_replace('/\D+/','',(string)$request->input($field))]);}}
    private function paymentPeriod(Carbon $start,string $cycle,int $count):array
    {
        if($cycle==='DAILY'){$end=$start->copy()->addDays($count-1);$label=$count===1?$start->translatedFormat('d F Y'):$start->translatedFormat('d M Y').' – '.$end->translatedFormat('d M Y');return[$label,$end];}
        if($cycle==='WEEKLY'){$end=$start->copy()->addWeeks($count)->subDay();return[$start->translatedFormat('d M Y').' – '.$end->translatedFormat('d M Y'),$end];}
        $lastStart=$start->copy()->addMonthsNoOverflow($count-1);$end=$start->copy()->addMonthsNoOverflow($count)->subDay();$label=$count===1?$start->translatedFormat('F Y'):$start->translatedFormat('F Y').' – '.$lastStart->translatedFormat('F Y');return[$label,$end];
    }
}
