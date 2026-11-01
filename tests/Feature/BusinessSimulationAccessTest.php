<?php

namespace Tests\Feature;

use App\Models\BusinessSimulation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessSimulationAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_open_simulation_and_manage_costs(): void
    {
        $owner = User::create(['name'=>'Owner','email'=>'owner@example.test','password'=>'secret123','role'=>'OWNER']);

        $this->actingAs($owner)->get(route('business-simulation.index'))
            ->assertOk()
            ->assertSee('Simulasi Bisnis')
            ->assertSee('Data aktual yang dipakai');

        $simulation = BusinessSimulation::firstOrFail();
        $this->actingAs($owner)->post(route('business-simulation.costs.store', $simulation), [
            'type'=>'OPERATIONAL',
            'applies_to'=>'HYBRID',
            'name'=>'Laundry linen',
            'amount'=>1_500_000,
            'notes'=>'Estimasi bulanan',
        ])->assertRedirect(route('business-simulation.index'));

        $this->assertDatabaseHas('business_simulation_costs', [
            'business_simulation_id'=>$simulation->id,
            'name'=>'Laundry linen',
            'amount'=>1_500_000,
        ]);
    }

    public function test_non_owner_cannot_access_business_simulation(): void
    {
        $staff = User::create(['name'=>'Staff','email'=>'staff@example.test','password'=>'secret123','role'=>'PENJAGA']);

        $this->actingAs($staff)->get(route('business-simulation.index'))->assertForbidden();
    }
}
