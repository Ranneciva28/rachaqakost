<?php

namespace App\Http\Controllers;

use App\Models\{AppSetting, Expense, ExpenseCategory, Maintenance, Payment, Room, RoomCategory, Tenant, User};
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class KostController extends Controller
{
    private const DEFAULT_WHATSAPP_TEMPLATE = "Halo {nama}, kami dari RachaqaKost ingin mengingatkan pembayaran sewa kamar #{kamar} sebesar {nominal} yang {status}, tepatnya pada {jatuh_tempo}. Mohon konfirmasinya ya. Terima kasih.";

    public function index(Request $request)
    {
        [$reportFrom, $reportTo] = $this->reportRange($request);
        $rooms = Room::with(['category', 'activeTenant'])->orderBy('floor')->orderBy('number')->get();
        $tenants = Tenant::with('room.category')->where('active', true)->orderBy('next_due')->get();
        $cashflow = collect(range(5, 0))->map(function (int $ago) {
            $date = now()->subMonths($ago);
            $range = [$date->copy()->startOfMonth(), $date->copy()->endOfMonth()];
            return ['label'=>$date->translatedFormat('M'),'income'=>(float)Payment::whereBetween('paid_at',$range)->sum('amount'),'expense'=>(float)Expense::whereBetween('spent_at',$range)->sum('amount')];
        });
        $incomeQuery = Payment::whereBetween('paid_at', [$reportFrom->toDateString(), $reportTo->toDateString()]);
        $expenseQuery = Expense::whereBetween('spent_at', [$reportFrom->toDateString(), $reportTo->toDateString()]);
        $income = (float) (clone $incomeQuery)->sum('amount');
        $expenseTotal = (float) (clone $expenseQuery)->sum('amount');
        $activeTab = $request->string('tab')->value() ?: 'dashboard';
        $allowed = ['dashboard','rooms','tenants','payments','expenses','maintenance','users'];
        if (!in_array($activeTab, $allowed, true) || ($activeTab === 'users' && !$request->user()->isOwner())) $activeTab = 'dashboard';

        return view('dashboard', [
            'activeTab'=>$activeTab, 'rooms'=>$rooms, 'categories'=>RoomCategory::withCount('rooms')->orderBy('name')->get(),
            'tenants'=>$tenants, 'tenantHistory'=>Tenant::with('room.category')->where('active',false)->latest('move_out')->limit(40)->get(),
            'payments'=>Payment::with(['tenant.room','recorder'])->latest('paid_at')->limit(80)->get(),
            'expenses'=>Expense::with('recorder')->latest('spent_at')->limit(80)->get(),
            'expenseCategories'=>ExpenseCategory::orderByDesc('is_system')->orderBy('name')->get(),
            'maintenances'=>Maintenance::with(['room','recorder'])->latest('reported_at')->get(),
            'income'=>$income, 'expenseTotal'=>$expenseTotal, 'profit'=>$income-$expenseTotal,
            'incomeTransactionCount'=>(clone $incomeQuery)->count(), 'expenseTransactionCount'=>(clone $expenseQuery)->count(),
            'reportFrom'=>$reportFrom, 'reportTo'=>$reportTo,
            'dueSoon'=>$tenants->filter(fn(Tenant $t)=>$t->next_due->lte(now()->addDays(7))),
            'cashflow'=>$cashflow, 'maxCashflow'=>max(1,(float)$cashflow->flatMap(fn($p)=>[$p['income'],$p['expense']])->max()),
            'users'=>$request->user()->isOwner()?User::orderBy('name')->get():collect(),
            'whatsappTemplate'=>AppSetting::where('key','whatsapp_payment_template')->value('value') ?: self::DEFAULT_WHATSAPP_TEMPLATE,
        ]);
    }

    public function storeCategory(Request $request) { $this->ownerOnly($request); RoomCategory::create($this->categoryData($request)); return back()->with('success','Kategori berhasil ditambahkan.'); }
    public function updateCategory(Request $request, RoomCategory $category) { $this->ownerOnly($request); $category->update($this->categoryData($request,$category)); return back()->with('success','Kategori dan harga diperbarui.'); }
    public function destroyCategory(Request $request, RoomCategory $category) { $this->ownerOnly($request); abort_if($category->rooms()->exists(),422,'Pindahkan semua kamar sebelum menghapus kategori.'); $category->delete(); return back()->with('success','Kategori dihapus.'); }

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

    public function tenantIn(Request $request)
    {
        $data=$request->validate(['room_id'=>['required','exists:rooms,id'],'name'=>['required','max:120'],'phone'=>['required','max:30'],'identity_number'=>['nullable','max:40'],'move_in'=>['required','date'],'next_due'=>['required','date','after_or_equal:move_in']]);
        $room=Room::findOrFail($data['room_id']); abort_if($room->status!=='KOSONG'||$room->activeTenant()->exists(),422,'Kamar tidak tersedia.');
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

    public function payment(Request $request)
    {
        $this->normalizeCurrencyFields($request, ['amount']);
        $data=$request->validate(['tenant_id'=>['required','exists:tenants,id'],'amount'=>['required','numeric','min:1'],'paid_at'=>['required','date'],'method'=>['required',Rule::in(['Transfer','Cash','QRIS'])],'months'=>['required','integer','min:1','max:24']]);
        DB::transaction(function()use($data,$request){
            $tenant=Tenant::where('active',true)->findOrFail($data['tenant_id']);
            $periodStart=Carbon::parse($tenant->next_due);
            $periodEnd=$periodStart->copy()->addMonthsNoOverflow((int)$data['months']-1);
            $period=$periodStart->isSameMonth($periodEnd)
                ?$periodStart->translatedFormat('F Y')
                :$periodStart->translatedFormat('F Y').' – '.$periodEnd->translatedFormat('F Y');
            Payment::create([
                'tenant_id'=>$tenant->id,
                'amount'=>$data['amount'],
                'paid_at'=>$data['paid_at'],
                'period'=>$period,
                'method'=>$data['method'],
                'recorded_by'=>$request->user()->id,
            ]);
            $tenant->update(['next_due'=>$periodStart->addMonthsNoOverflow((int)$data['months'])]);
        });
        return back()->with('success','Pembayaran tersimpan; jatuh tempo otomatis diperbarui.');
    }

    public function expense(Request $request)
    {
        $this->normalizeCurrencyFields($request, ['amount']);
        $data=$request->validate(['title'=>['required','max:150'],'category'=>['required',Rule::exists('expense_categories','name')],'amount'=>['required','numeric','min:1'],'spent_at'=>['required','date'],'notes'=>['nullable','max:500']]);
        Expense::create($data+['recorded_by'=>$request->user()->id]); return back()->with('success','Pengeluaran dicatat.');
    }

    public function storeExpenseCategory(Request $request)
    {
        $this->ownerOnly($request);
        ExpenseCategory::create($this->expenseCategoryData($request));

        return back()->with('success','Kategori pengeluaran ditambahkan.');
    }

    public function updateExpenseCategory(Request $request, ExpenseCategory $expenseCategory)
    {
        $this->ownerOnly($request);
        $oldName=$expenseCategory->name;
        $data=$this->expenseCategoryData($request,$expenseCategory);
        abort_if($expenseCategory->is_system&&$oldName!==$data['name'],422,'Nama kategori sistem Maintenance tidak dapat diubah.');
        DB::transaction(function()use($expenseCategory,$oldName,$data){
            $expenseCategory->update($data);
            if($oldName!==$data['name'])Expense::where('category',$oldName)->update(['category'=>$data['name']]);
        });

        return back()->with('success','Kategori pengeluaran diperbarui.');
    }

    public function destroyExpenseCategory(Request $request, ExpenseCategory $expenseCategory)
    {
        $this->ownerOnly($request);
        abort_if($expenseCategory->is_system,422,'Kategori sistem Maintenance tidak dapat dihapus.');
        $expenseCategory->delete();

        return back()->with('success','Kategori dihapus; histori pengeluaran tetap tersimpan.');
    }

    public function maintenance(Request $request)
    {
        $this->normalizeCurrencyFields($request, ['cost']);
        $data=$request->validate(['room_id'=>['required','exists:rooms,id'],'title'=>['required','max:150'],'status'=>['required',Rule::in(['DIJADWALKAN','DIKERJAKAN'])],'cost'=>['nullable','numeric','min:0'],'reported_at'=>['required','date'],'notes'=>['nullable','max:500']]);
        DB::transaction(function()use($data,$request){Maintenance::create($data+['recorded_by'=>$request->user()->id]);Room::whereKey($data['room_id'])->update(['status'=>'MAINTENANCE']);});
        return back()->with('success','Tiket maintenance dibuat.');
    }

    public function maintenanceDone(Request $request, Maintenance $maintenance)
    {
        abort_if($maintenance->status==='SELESAI',422,'Maintenance sudah selesai.');
        $this->normalizeCurrencyFields($request, ['cost']);
        $data=$request->validate(['completed_at'=>['required','date','after_or_equal:'.$maintenance->reported_at->toDateString()],'cost'=>['required','numeric','min:0'],'notes'=>['nullable','max:500']]);
        DB::transaction(function()use($maintenance,$data,$request){$expense=(float)$data['cost']>0?Expense::create(['title'=>'Maintenance kamar #'.$maintenance->room->number.': '.$maintenance->title,'category'=>'Maintenance','amount'=>$data['cost'],'spent_at'=>$data['completed_at'],'notes'=>$data['notes']?:$maintenance->notes,'recorded_by'=>$request->user()->id]):null;$maintenance->update(['status'=>'SELESAI','completed_at'=>$data['completed_at'],'cost'=>$data['cost'],'notes'=>$data['notes']?:$maintenance->notes,'expense_id'=>$expense?->id]);$maintenance->room->update(['status'=>$maintenance->room->activeTenant()->exists()?'TERISI':'KOSONG']);});
        return back()->with('success','Maintenance selesai dan masuk histori.');
    }

    public function updateWhatsAppTemplate(Request $request)
    {
        $this->ownerOnly($request);
        $data=$request->validate([
            'template'=>['required','string','max:1500'],
        ]);
        AppSetting::updateOrCreate(
            ['key'=>'whatsapp_payment_template'],
            ['value'=>$data['template'],'updated_by'=>$request->user()->id],
        );

        return back()->with('success','Template follow-up WhatsApp diperbarui.');
    }

    private function ownerOnly(Request $request):void { abort_unless($request->user()->isOwner(),403); }
    private function categoryData(Request $request,?RoomCategory $category=null):array { $this->normalizeCurrencyFields($request,['monthly_price']); return $request->validate(['name'=>['required','max:80',Rule::unique('room_categories','name')->ignore($category)],'monthly_price'=>['required','numeric','min:0'],'color'=>['required','regex:/^#[0-9A-Fa-f]{6}$/']]); }
    private function expenseCategoryData(Request $request,?ExpenseCategory $category=null):array { return $request->validate(['name'=>['required','max:60',Rule::unique('expense_categories','name')->ignore($category)],'color'=>['required','regex:/^#[0-9A-Fa-f]{6}$/']]); }
    private function reportRange(Request $request):array { $from=$request->string('from')->value();$to=$request->string('to')->value();$from=Carbon::hasFormat($from,'Y-m-d')?Carbon::createFromFormat('Y-m-d',$from)->startOfDay():now()->startOfMonth();$to=Carbon::hasFormat($to,'Y-m-d')?Carbon::createFromFormat('Y-m-d',$to)->endOfDay():now()->endOfDay();if($from->gt($to))[$from,$to]=[$to->copy()->startOfDay(),$from->copy()->endOfDay()];return[$from,$to]; }
    private function normalizeCurrencyFields(Request $request,array $fields):void { foreach($fields as $field){if($request->filled($field))$request->merge([$field=>preg_replace('/\D+/','',(string)$request->input($field))]);} }
}
