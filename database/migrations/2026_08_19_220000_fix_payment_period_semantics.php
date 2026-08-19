<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $tenants = DB::table('tenants')->orderBy('id')->get(['id', 'move_in', 'next_due', 'active']);

            foreach ($tenants as $tenant) {
                if (! $tenant->move_in) continue;

                $cursor = Carbon::parse($tenant->move_in)->startOfDay();
                $regularPayments = DB::table('payments')
                    ->where('tenant_id', $tenant->id)
                    ->where('is_historical', false)
                    ->orderBy('paid_at')
                    ->orderBy('id')
                    ->get(['id', 'billing_cycle', 'period_count']);

                $lastCoverageEnd = null;
                foreach ($regularPayments as $payment) {
                    [$period, $coverageEnd] = $this->periodDetails($cursor, $payment->billing_cycle ?: 'MONTHLY', max(1, (int) $payment->period_count));

                    DB::table('payments')->where('id', $payment->id)->update([
                        'period' => $period,
                        'coverage_start' => $cursor->toDateString(),
                        'coverage_end' => $coverageEnd->toDateString(),
                        'updated_at' => now(),
                    ]);

                    $lastCoverageEnd = $coverageEnd->copy();
                    $cursor = $coverageEnd->copy()->addDay();
                }

                if ($lastCoverageEnd && $tenant->active) {
                    DB::table('tenants')->where('id', $tenant->id)->update([
                        'next_due' => $lastCoverageEnd->toDateString(),
                        'updated_at' => now(),
                    ]);
                }
            }

            DB::table('payments')
                ->where('is_historical', true)
                ->whereNotNull('coverage_start')
                ->orderBy('id')
                ->get(['id', 'coverage_start', 'billing_cycle', 'period_count'])
                ->each(function ($payment) {
                    $start = Carbon::parse($payment->coverage_start)->startOfDay();
                    [$period, $coverageEnd] = $this->periodDetails($start, $payment->billing_cycle ?: 'MONTHLY', max(1, (int) $payment->period_count));

                    DB::table('payments')->where('id', $payment->id)->update([
                        'period' => $period,
                        'coverage_end' => $coverageEnd->toDateString(),
                        'updated_at' => now(),
                    ]);
                });
        });
    }

    public function down(): void
    {
        // Data-period correction is intentionally irreversible: no financial values are changed.
    }

    private function periodDetails(Carbon $start, string $cycle, int $count): array
    {
        if ($cycle === 'DAILY') {
            $end = $start->copy()->addDays($count - 1);
            $label = $count === 1
                ? $start->translatedFormat('d F Y')
                : $start->translatedFormat('d M Y').' – '.$end->translatedFormat('d M Y');

            return [$label, $end];
        }

        if ($cycle === 'WEEKLY') {
            $end = $start->copy()->addWeeks($count)->subDay();

            return [$start->translatedFormat('d M Y').' – '.$end->translatedFormat('d M Y'), $end];
        }

        $lastStart = $start->copy()->addMonthsNoOverflow($count - 1);
        $end = $start->copy()->addMonthsNoOverflow($count)->subDay();
        $label = $count === 1
            ? $start->translatedFormat('F Y')
            : $start->translatedFormat('F Y').' – '.$lastStart->translatedFormat('F Y');

        return [$label, $end];
    }
};
