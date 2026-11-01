<?php

namespace App\Http\Controllers;

use App\Models\BusinessSimulation;
use App\Models\BusinessSimulationCost;
use App\Services\BusinessSimulationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BusinessSimulationController extends Controller
{
    public function index(Request $request, BusinessSimulationService $calculator)
    {
        $this->ownerOnly($request);
        $simulation = BusinessSimulation::query()->with('costs')->firstOrCreate(
            [],
            [
                'name' => 'Simulasi Utama',
                'daily_occupancy' => 70,
                'ota_commission' => 15,
                'projection_days' => 30,
                'created_by' => $request->user()->id,
            ]
        );
        $simulation->loadMissing('costs');
        $facts = $calculator->roomFacts();
        $metrics = $calculator->calculate($simulation, $facts);

        return view('business-simulation', compact('simulation', 'facts', 'metrics'));
    }

    public function update(Request $request, BusinessSimulation $simulation)
    {
        $this->ownerOnly($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'daily_occupancy' => ['required', 'numeric', 'min:0', 'max:100'],
            'daily_rate' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'ota_commission' => ['required', 'numeric', 'min:0', 'max:100'],
            'projection_days' => ['required', 'integer', 'min:1', 'max:366'],
        ]);
        $simulation->update($data);

        return redirect()->route('business-simulation.index')->with('success', 'Asumsi simulasi berhasil disimpan.');
    }

    public function storeCost(Request $request, BusinessSimulation $simulation)
    {
        $this->ownerOnly($request);
        $simulation->costs()->create($this->costData($request));

        return redirect()->route('business-simulation.index')->with('success', 'Komponen biaya berhasil ditambahkan.');
    }

    public function updateCost(Request $request, BusinessSimulationCost $cost)
    {
        $this->ownerOnly($request);
        $cost->update($this->costData($request));

        return redirect()->route('business-simulation.index')->with('success', 'Komponen biaya berhasil diperbarui.');
    }

    public function destroyCost(Request $request, BusinessSimulationCost $cost)
    {
        $this->ownerOnly($request);
        $cost->delete();

        return redirect()->route('business-simulation.index')->with('success', 'Komponen biaya berhasil dihapus.');
    }

    private function costData(Request $request): array
    {
        return $request->validate([
            'type' => ['required', Rule::in(['OPERATIONAL', 'INVESTMENT'])],
            'applies_to' => ['required', Rule::in(['FULL_MONTHLY', 'HYBRID', 'BOTH'])],
            'name' => ['required', 'string', 'max:120'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999999999'],
            'notes' => ['nullable', 'string', 'max:300'],
        ]);
    }

    private function ownerOnly(Request $request): void
    {
        abort_unless($request->user()?->isOwner(), 403);
    }
}
