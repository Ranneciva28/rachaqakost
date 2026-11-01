<?php

namespace Tests\Unit;

use App\Models\BusinessSimulation;
use App\Models\BusinessSimulationCost;
use App\Models\Room;
use App\Models\RoomCategory;
use App\Services\BusinessSimulationService;
use PHPUnit\Framework\TestCase;

class BusinessSimulationServiceTest extends TestCase
{
    public function test_it_compares_full_monthly_and_hybrid_without_using_transaction_data(): void
    {
        $rooms = collect([
            ...$this->rooms(11, 1, 'Standard', 150_000, 1_500_000, 1),
            ...$this->rooms(13, 2, 'Premium', 200_000, 1_600_000, 2),
        ]);
        $simulation = new BusinessSimulation([
            'daily_occupancy' => 70,
            'daily_rate' => 150_000,
            'ota_commission' => 15,
            'projection_days' => 30,
        ]);
        $costs = collect([
            new BusinessSimulationCost(['type'=>'OPERATIONAL','applies_to'=>'FULL_MONTHLY','name'=>'Operasional A','amount'=>3_000_000]),
            new BusinessSimulationCost(['type'=>'OPERATIONAL','applies_to'=>'HYBRID','name'=>'Operasional B','amount'=>5_000_000]),
            new BusinessSimulationCost(['type'=>'INVESTMENT','applies_to'=>'HYBRID','name'=>'Renovasi','amount'=>100_000_000]),
        ]);

        $service = new BusinessSimulationService();
        $facts = $service->roomFacts($rooms);
        $result = $service->calculate($simulation, $facts, $costs);

        $this->assertSame(24, $facts['total_rooms']);
        $this->assertSame(11, $facts['daily_rooms']);
        $this->assertSame(13, $facts['monthly_rooms']);
        $this->assertEqualsWithDelta(37_300_000, $result['full']['gross_revenue'], 0.01);
        $this->assertEqualsWithDelta(34_300_000, $result['full']['net_profit'], 0.01);
        $this->assertEqualsWithDelta(55_450_000, $result['hybrid']['gross_revenue'], 0.01);
        $this->assertEqualsWithDelta(5_197_500, $result['hybrid']['ota_fee'], 0.01);
        $this->assertEqualsWithDelta(45_252_500, $result['hybrid']['net_profit'], 0.01);
        $this->assertEqualsWithDelta(10_952_500, $result['difference'], 0.01);
        $this->assertEqualsWithDelta(43.97, $result['hybrid_parity_occupancy'], 0.01);
        $this->assertEqualsWithDelta(2.21, $result['hybrid']['payback_months'], 0.01);
    }

    public function test_shared_cost_is_applied_to_both_scenarios(): void
    {
        $rooms = collect($this->rooms(1, 1, 'Standard', 100_000, 1_000_000, 1));
        $simulation = new BusinessSimulation(['daily_occupancy'=>100,'ota_commission'=>0,'projection_days'=>30]);
        $costs = collect([new BusinessSimulationCost(['type'=>'OPERATIONAL','applies_to'=>'BOTH','name'=>'Internet','amount'=>500_000])]);
        $service = new BusinessSimulationService();

        $result = $service->calculate($simulation, $service->roomFacts($rooms), $costs);

        $this->assertEqualsWithDelta(500_000, $result['full']['operating_cost'], 0.01);
        $this->assertEqualsWithDelta(500_000, $result['hybrid']['operating_cost'], 0.01);
    }

    private function rooms(int $count, int $floor, string $name, float $daily, float $monthly, int $categoryId): array
    {
        $category = new RoomCategory(['name'=>$name,'daily_price'=>$daily,'weekly_price'=>0,'monthly_price'=>$monthly]);
        $category->id = $categoryId;

        return collect(range(1, $count))->map(function (int $number) use ($floor, $category, $categoryId) {
            $room = new Room(['number'=>(string)$number,'floor'=>$floor,'room_category_id'=>$categoryId,'status'=>'KOSONG']);
            $room->setRelation('category', $category);
            return $room;
        })->all();
    }
}
