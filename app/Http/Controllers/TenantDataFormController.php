<?php

namespace App\Http\Controllers;

use App\Models\{AppSetting, Tenant, TenantDataForm};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TenantDataFormController extends Controller
{
    public function publicShow(string $token)
    {
        $tenant = Tenant::with(['room.category', 'tenantForm'])->where('form_token', $token)->firstOrFail();
        return response()->view('tenant-forms.public', ['tenant' => $tenant, 'form' => $tenant->tenantForm])
            ->header('X-Robots-Tag', 'noindex, nofollow, noarchive')
            ->header('Referrer-Policy', 'no-referrer');
    }

    public function publicSubmit(Request $request, string $token)
    {
        $tenant = Tenant::with('tenantForm')->where('form_token', $token)->firstOrFail();
        abort_if($tenant->tenantForm && $tenant->tenantForm->status !== 'REVISION', 423, 'Formulir sudah dikunci. Hubungi pengelola RachaqaKost untuk membuka revisi.');
        $data = $this->formData($request);

        DB::transaction(function () use ($tenant, $data) {
            $tenant->tenantForm()->updateOrCreate([], $data + [
                'status' => 'PENDING_APPROVAL',
                'submitted_at' => now(),
                'validated_by' => null,
                'validated_at' => null,
                'revision_opened_at' => null,
            ]);
        });

        return redirect()->route('tenant-form.public', $tenant->form_token)->with('success', 'Formulir berhasil dikirim dan sedang menunggu validasi pengelola.');
    }

    public function attachment(Request $request, Tenant $tenant)
    {
        $form = $tenant->tenantForm()->with('validator')->firstOrFail();
        return view('tenant-forms.attachment', compact('tenant', 'form'));
    }

    public function validateForm(Request $request, Tenant $tenant)
    {
        $this->ownerOnly($request);
        $form = $tenant->tenantForm()->firstOrFail();
        abort_unless($form->status === 'PENDING_APPROVAL', 422, 'Hanya draft yang menunggu approval yang dapat divalidasi.');

        DB::transaction(function () use ($tenant, $form, $request) {
            $form->update(['status' => 'VALID', 'validated_by' => $request->user()->id, 'validated_at' => now(), 'revision_opened_at' => null]);
            $tenant->update(['name' => $form->full_name, 'phone' => $form->phone, 'identity_number' => $form->identity_number]);
        });

        return back()->with('success', 'Formulir sudah valid dan data utama penghuni disinkronkan.');
    }

    public function requestRevision(Request $request, Tenant $tenant)
    {
        $this->ownerOnly($request);
        $form = $tenant->tenantForm()->firstOrFail();
        abort_if($form->status === 'REVISION', 422, 'Formulir sudah berada dalam mode revisi.');
        $form->update(['status' => 'REVISION', 'validated_by' => null, 'validated_at' => null, 'revision_opened_at' => now()]);
        return back()->with('success', 'Mode revisi dibuka. Penghuni dapat mengubah formulir melalui link yang sama.');
    }

    public function updateTemplate(Request $request)
    {
        $this->ownerOnly($request);
        $data = $request->validate(['template' => ['required', 'string', 'max:1500']]);
        AppSetting::updateOrCreate(['key' => 'tenant_form_whatsapp_template'], ['value' => $data['template'], 'updated_by' => $request->user()->id]);
        return back()->with('success', 'Template WhatsApp formulir diperbarui.');
    }

    private function ownerOnly(Request $request): void
    {
        abort_unless($request->user()->isOwner(), 403);
    }

    private function formData(Request $request): array
    {
        return $request->validate([
            'full_name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+() .-]{9,30}$/'],
            'identity_number' => ['required', 'string', 'max:40', 'regex:/^[0-9]{12,20}$/'],
            'email' => ['nullable', 'email', 'max:150'],
            'birth_place' => ['required', 'string', 'max:100'],
            'birth_date' => ['required', 'date', 'before:today'],
            'gender' => ['required', Rule::in(['Laki-laki', 'Perempuan'])],
            'occupation' => ['required', 'string', 'max:120'],
            'employer_or_school' => ['nullable', 'string', 'max:150'],
            'identity_address' => ['required', 'string', 'max:1000'],
            'domicile_address' => ['required', 'string', 'max:1000'],
            'emergency_name' => ['required', 'string', 'max:120'],
            'emergency_relationship' => ['required', 'string', 'max:60'],
            'emergency_phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+() .-]{9,30}$/'],
            'vehicle_type' => ['nullable', 'string', 'max:60'],
            'vehicle_plate' => ['nullable', 'string', 'max:20'],
            'additional_notes' => ['nullable', 'string', 'max:1000'],
        ]);
    }
}
