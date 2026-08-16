<?php

namespace App\Http\Controllers;

use App\Models\{Expense, Maintenance, Payment, Room, RoomCategory, Tenant, User};
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class KostController extends Controller
{
    public function index(Request $request)
    {
        $month = now()->startOfMonth();
        $rooms = Room::with(['category', 'activeTenant'])->orderBy('floor')->orderBy('number')->get();
        $tenants = Tenant::with('room.category')->where('active', true)->orderBy('next_due')->get();
        $cashflow = collect(range(5, 0))->map(function (int $ago) {
            $date = now()->subMonths($ago);
            $range = [$date->copy()->startOfMonth(), $date->copy()->endOfMonth()];
            return ['label'=>$date->translatedFormat('M'),'income'=>(float)Payment::whereBetween('paid_at',$range)->sum('amount'),'expense'=>(float)Expense::whereBetween('spent_at',$range)->sum('amount')];
        });
        $income = (float) Payment::where('paid_at', '>=', $month)->sum('amount');
        $expenseTotal = (float) Expense::where('spent_at', '>=', $month)->sum('amount');
        $activeTab = $request->string('tab')->value() ?: 'dashboard';
        $allowed = ['dashboard','rooms','tenants','payments','expenses','maintenance','users'];
        if (!in_array($activeTab, $allowed, true) || ($activeTab === 'users' && !$request->user()->isOwner())) $activeTab = 'dashboard';

        return view('dashboard', [
            'activeTab'=>$activeTab, 'rooms'=>$rooms, 'categories'=>RoomCategory::withCount('rooms')->orderBy('name')->get(),
            'tenants'=>$tenants, 'tenantHistory'=>Tenant::with('room.category')->where('active',false)->latest('move_out')->limit(40)->get(),
            'payments'=>Payment::with(['tenant.room','recorder'])->latest('paid_at')->limit(80)->get(),
            'expenses'=>Expense::with('recorder')->latest('spent_at')->limit(80)->get(),
            'maintenances'=>Maintenance::with(['room','recorder'])->latest('reported_at')->get(),
            'income'=>$income, 'expenseTotal'=>$expenseTotal, 'profit'=>$income-$expenseTotal,
            'dueSoon'=>$tenants->filter(fn(Tenant $t)=>$t->next_due->lte(now()->addDays(7))),
            'cashflow'=>$cashflow, 'maxCashflow'=>max(1,(float)$cashflow->flatMap(fn($p)=>[$p['income'],$p['expense']])->max()),
            'users'=>$request->user()->isOwner()?User::orderBy('name')->get():collect(),
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
        $data=$request->validate(['tenant_id'=>['required','exists:tenants,id'],'amount'=>['required','numeric','min:1'],'paid_at'=>['required','date'],'period'=>['required','max:50'],'method'=>['required',Rule::in(['Transfer','Cash','QRIS'])],'months'=>['required','integer','min:1','max:24']]);
        DB::transaction(function()use($data,$request){$tenant=Tenant::where('active',true)->findOrFail($data['tenant_id']);Payment::create(collect($data)->except('months')->all()+['recorded_by'=>$request->user()->id]);$tenant->update(['next_due'=>Carbon::parse($tenant->next_due)->addMonthsNoOverflow((int)$data['months'])]);});
        return back()->with('success','Pembayaran tersimpan; jatuh tempo otomatis diperbarui.');
    }

    public function expense(Request $request)
    {
        $data=$request->validate(['title'=>['required','max:150'],'category'=>['required','max:60'],'amount'=>['required','numeric','min:1'],'spent_at'=>['required','date'],'notes'=>['nullable','max:500']]);
        Expense::create($data+['recorded_by'=>$request->user()->id]); return back()->with('success','Pengeluaran dicatat.');
    }

    public function maintenance(Request $request)
    {
        $data=$request->validate(['room_id'=>['required','exists:rooms,id'],'title'=>['required','max:150'],'status'=>['required',Rule::in(['DIJADWALKAN','DIKERJAKAN'])],'cost'=>['nullable','numeric','min:0'],'reported_at'=>['required','date'],'notes'=>['nullable','max:500']]);
        DB::transaction(function()use($data,$request){Maintenance::create($data+['recorded_by'=>$request->user()->id]);Room::whereKey($data['room_id'])->update(['status'=>'MAINTENANCE']);});
        return back()->with('success','Tiket maintenance dibuat.');
    }

    public function maintenanceDone(Request $request, Maintenance $maintenance)
    {
        abort_if($maintenance->status==='SELESAI',422,'Maintenance sudah selesai.');
        $data=$request->validate(['completed_at'=>['required','date','after_or_equal:'.$maintenance->reported_at->toDateString()],'cost'=>['required','numeric','min:0'],'notes'=>['nullable','max:500']]);
        DB::transaction(function()use($maintenance,$data,$request){$expense=(float)$data['cost']>0?Expense::create(['title'=>'Maintenance kamar #'.$maintenance->room->number.': '.$maintenance->title,'category'=>'Maintenance','amount'=>$data['cost'],'spent_at'=>$data['completed_at'],'notes'=>$data['notes']?:$maintenance->notes,'recorded_by'=>$request->user()->id]):null;$maintenance->update(['status'=>'SELESAI','completed_at'=>$data['completed_at'],'cost'=>$data['cost'],'notes'=>$data['notes']?:$maintenance->notes,'expense_id'=>$expense?->id]);$maintenance->room->update(['status'=>$maintenance->room->activeTenant()->exists()?'TERISI':'KOSONG']);});
        return back()->with('success','Maintenance selesai dan masuk histori.');
    }

    private function ownerOnly(Request $request):void { abort_unless($request->user()->isOwner(),403); }
    private function categoryData(Request $request,?RoomCategory $category=null):array { return $request->validate(['name'=>['required','max:80',Rule::unique('room_categories','name')->ignore($category)],'monthly_price'=>['required','numeric','min:0'],'color'=>['required','regex:/^#[0-9A-Fa-f]{6}$/']]); }
}
