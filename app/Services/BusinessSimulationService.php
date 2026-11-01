<?php

namespace App\Services;

use App\Models\BusinessSimulation;
use App\Models\BusinessSimulationCost;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Room;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class BusinessSimulationService
{
    public function roomFacts(?Collection $rooms = null): array
    {
        $rooms ??= Room::with('category')->orderBy('floor')->orderBy('number')->get();

        $rows = $rooms->groupBy(fn (Room $room) => $room->floor.'-'.$room->room_category_id)
            ->map(function (Collection $group) {
                $first = $group->first();

                return [
                    'floor' => (int) $first->floor,
                    'category' => $first->category->name,
                    'rooms' => $group->count(),
                    'daily_rate' => (float) $first->category->daily_price,
                    'monthly_rate' => (float) $first->category->monthly_price,
                ];
            })->sortBy(fn (array $row) => sprintf('%03d-%s', $row['floor'], $row['category']))->values();

        $floorOne = $rooms->where('floor', 1);
        $upperFloors = $rooms->where('floor', '>=', 2);
        $weightedDailyRate = $floorOne->count() > 0
            ? $floorOne->sum(fn (Room $room) => (float) $room->category->daily_price) / $floorOne->count()
            : 0.0;

        return [
            'total_rooms' => $rooms->count(),
            'daily_rooms' => $floorOne->count(),
            'monthly_rooms' => $upperFloors->count(),
            'actual_daily_rate' => round($weightedDailyRate, 2),
            'full_monthly_revenue' => (float) $rooms->sum(fn (Room $room) => (float) $room->category->monthly_price),
            'hybrid_monthly_revenue' => (float) $upperFloors->sum(fn (Room $room) => (float) $room->category->monthly_price),
            'rows' => $rows,
        ];
    }

    public function actualPeriodFacts(Carbon $month): array
    {
        $from = $month->copy()->startOfMonth();
        $to = $month->copy()->endOfMonth();
        $payments = Payment::whereBetween('paid_at', [$from->toDateString(), $to->toDateString()]);
        $expenses = Expense::whereBetween('spent_at', [$from->toDateString(), $to->toDateString()]);

        return [
            'period' => $from->format('Y-m'),
            'label' => $from->translatedFormat('F Y'),
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'revenue' => (float) (clone $payments)->sum('amount'),
            'expense' => (float) (clone $expenses)->sum('amount'),
            'payment_count' => (clone $payments)->count(),
            'expense_count' => (clone $expenses)->count(),
        ];
    }

    public function calculate(BusinessSimulation $simulation, array $facts, array $actual, ?Collection $costs = null): array
    {
        $costs ??= $simulation->relationLoaded('costs') ? $simulation->costs : $simulation->costs()->get();
        $occupancy = max(0, min(100, (float) $simulation->daily_occupancy)) / 100;
        $commission = max(0, min(100, (float) $simulation->ota_commission)) / 100;
        $dailyRate = $simulation->daily_rate !== null ? (float) $simulation->daily_rate : (float) $facts['actual_daily_rate'];
        $days = max(1, (int) $simulation->projection_days);

        $hybridOperating = $this->costTotal($costs, 'OPERATIONAL', 'HYBRID');
        $hybridInvestment = $this->costTotal($costs, 'INVESTMENT', 'HYBRID');

        $actualRevenue = (float) $actual['revenue'];
        $actualExpense = (float) $actual['expense'];
        $actualNet = $actualRevenue - $actualExpense;

        $dailyGross = (float) $facts['daily_rooms'] * $dailyRate * $days * $occupancy;
        $otaFee = $dailyGross * $commission;
        $hybridGross = $dailyGross + (float) $facts['hybrid_monthly_revenue'];
        $hybridRevenue = $hybridGross - $otaFee;
        $hybridNet = $hybridRevenue - $hybridOperating;
        $dailyNetAtFullOccupancy = (float) $facts['daily_rooms'] * $dailyRate * $days * (1 - $commission);

        return [
            'daily_rate' => $dailyRate,
            'actual' => $this->scenarioMetrics($actualRevenue, 0, $actualRevenue, $actualExpense, 0),
            'hybrid' => $this->scenarioMetrics($hybridGross, $otaFee, $hybridRevenue, $hybridOperating, $hybridInvestment),
            'difference' => $hybridNet - $actualNet,
            'hybrid_break_even_occupancy' => $dailyNetAtFullOccupancy > 0
                ? $this->percentage(max(0, $hybridOperating - (float) $facts['hybrid_monthly_revenue']) / $dailyNetAtFullOccupancy)
                : null,
            'hybrid_parity_occupancy' => $dailyNetAtFullOccupancy > 0
                ? $this->percentage(max(0, $actualNet + $hybridOperating - (float) $facts['hybrid_monthly_revenue']) / $dailyNetAtFullOccupancy)
                : null,
        ];
    }

    private function costTotal(Collection $costs, string $type, string $scenario): float
    {
        return (float) $costs->filter(fn (BusinessSimulationCost $cost) =>
            $cost->type === $type && in_array($cost->applies_to, [$scenario, 'BOTH'], true)
        )->sum(fn (BusinessSimulationCost $cost) => (float) $cost->amount);
    }

    private function scenarioMetrics(float $gross, float $commission, float $revenue, float $operating, float $investment): array
    {
        $net = $revenue - $operating;

        return [
            'gross_revenue' => $gross,
            'ota_fee' => $commission,
            'net_revenue' => $revenue,
            'operating_cost' => $operating,
            'investment' => $investment,
            'net_profit' => $net,
            'roi' => $investment > 0 ? ($net * 12 / $investment) * 100 : null,
            'payback_months' => $investment > 0 && $net > 0 ? $investment / $net : null,
        ];
    }

    private function percentage(float $value): float
    {
        return round($value * 100, 2);
    }
}
