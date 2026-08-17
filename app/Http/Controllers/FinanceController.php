<?php

namespace App\Http\Controllers;

use App\Models\{Expense, ExpenseCategory, Payment};
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class FinanceController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->isOwner(), 403);

        [$from, $to] = $this->reportRange($request);
        $paymentRecords = Payment::whereBetween('paid_at', [$from->toDateString(), $to->toDateString()])->get(['amount', 'paid_at']);
        $expenseRecords = Expense::whereBetween('spent_at', [$from->toDateString(), $to->toDateString()])->get(['amount', 'spent_at', 'category']);
        $categoryMap = ExpenseCategory::all()->keyBy('name');

        $income = (float) $paymentRecords->sum('amount');
        $expenseTotal = (float) $expenseRecords->sum('amount');
        $directExpense = (float) $expenseRecords->filter(
            fn (Expense $expense) => $categoryMap->get($expense->category)?->cost_type === 'DIRECT'
        )->sum('amount');
        $operatingExpense = $expenseTotal - $directExpense;
        $grossProfit = $income - $directExpense;
        $netProfit = $income - $expenseTotal;

        $categories = $expenseRecords->groupBy('category')->map(function (Collection $records, string $name) use ($categoryMap, $expenseTotal) {
            $amount = (float) $records->sum('amount');
            $category = $categoryMap->get($name);

            return [
                'name'=>$name,
                'amount'=>$amount,
                'percentage'=>$expenseTotal > 0 ? round($amount / $expenseTotal * 100, 1) : 0,
                'color'=>$category?->color ?? '#7A8582',
                'cost_type'=>$category?->cost_type ?? 'OPERATING',
                'cost_behavior'=>$category?->cost_behavior ?? 'FIXED',
                'transactions'=>$records->count(),
            ];
        })->sortByDesc('amount')->values();

        $variableExpense = (float) $expenseRecords->filter(
            fn (Expense $expense) => $categoryMap->get($expense->category)?->cost_behavior === 'VARIABLE'
        )->sum('amount');
        $fixedExpense = $expenseTotal - $variableExpense;
        $days = $from->diffInDays($to) + 1;
        $previousTo = $from->copy()->subDay()->endOfDay();
        $previousFrom = $previousTo->copy()->subDays($days - 1)->startOfDay();
        $previousIncome = (float) Payment::whereBetween('paid_at', [$previousFrom->toDateString(), $previousTo->toDateString()])->sum('amount');
        $previousExpenses = Expense::whereBetween('spent_at', [$previousFrom->toDateString(), $previousTo->toDateString()])->get(['amount', 'category']);
        $previousExpenseTotal = (float) $previousExpenses->sum('amount');
        $previousNetProfit = $previousIncome - $previousExpenseTotal;

        $ratios = [
            ['label'=>'Gross profit margin', 'value'=>$this->percent($grossProfit, $income), 'help'=>'Laba kotor ÷ pendapatan'],
            ['label'=>'Net profit margin', 'value'=>$this->percent($netProfit, $income), 'help'=>'Laba bersih ÷ pendapatan'],
            ['label'=>'Expense ratio', 'value'=>$this->percent($expenseTotal, $income), 'help'=>'Total pengeluaran ÷ pendapatan', 'inverse'=>true],
            ['label'=>'Direct cost ratio', 'value'=>$this->percent($directExpense, $income), 'help'=>'Biaya langsung ÷ pendapatan', 'inverse'=>true],
            ['label'=>'Operating expense ratio', 'value'=>$this->percent($operatingExpense, $income), 'help'=>'Biaya operasional ÷ pendapatan', 'inverse'=>true],
            ['label'=>'Revenue coverage', 'value'=>$expenseTotal > 0 ? round($income / $expenseTotal, 2) : null, 'suffix'=>'×', 'help'=>'Pendapatan ÷ total pengeluaran'],
            ['label'=>'Return on expense', 'value'=>$this->percent($netProfit, $expenseTotal), 'help'=>'Laba bersih ÷ pengeluaran'],
            ['label'=>'Rata-rata pendapatan harian', 'money'=>$income / max(1, $days), 'help'=>$days.' hari dalam periode'],
            ['label'=>'Total variable cost', 'money'=>$variableExpense, 'help'=>($expenseTotal > 0 ? number_format($variableExpense / $expenseTotal * 100, 1, ',', '.').'%' : '0%').' dari seluruh pengeluaran'],
            ['label'=>'Total fixed cost', 'money'=>$fixedExpense, 'help'=>($expenseTotal > 0 ? number_format($fixedExpense / $expenseTotal * 100, 1, ',', '.').'%' : '0%').' dari seluruh pengeluaran'],
        ];

        $trend = $this->trend($from, $to, $paymentRecords, $expenseRecords);

        return view('finance', [
            'from'=>$from,
            'to'=>$to,
            'previousFrom'=>$previousFrom,
            'previousTo'=>$previousTo,
            'income'=>$income,
            'expenseTotal'=>$expenseTotal,
            'directExpense'=>$directExpense,
            'operatingExpense'=>$operatingExpense,
            'grossProfit'=>$grossProfit,
            'netProfit'=>$netProfit,
            'incomeTransactions'=>$paymentRecords->count(),
            'expenseTransactions'=>$expenseRecords->count(),
            'categories'=>$categories,
            'largestCategory'=>$categories->first(),
            'ratios'=>$ratios,
            'trend'=>$trend,
            'maxTrend'=>max(1, (float) $trend->flatMap(fn ($point) => [$point['income'], $point['expense']])->max()),
            'comparison'=>[
                'income'=>$this->change($income, $previousIncome),
                'expense'=>$this->change($expenseTotal, $previousExpenseTotal),
                'profit'=>$this->change($netProfit, $previousNetProfit),
            ],
        ]);
    }

    private function reportRange(Request $request): array
    {
        $fromInput = $request->string('from')->value();
        $toInput = $request->string('to')->value();
        $from = Carbon::hasFormat($fromInput, 'Y-m-d') ? Carbon::createFromFormat('Y-m-d', $fromInput)->startOfDay() : now()->startOfMonth();
        $to = Carbon::hasFormat($toInput, 'Y-m-d') ? Carbon::createFromFormat('Y-m-d', $toInput)->endOfDay() : now()->endOfDay();

        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return [$from, $to];
    }

    private function trend(Carbon $from, Carbon $to, Collection $payments, Collection $expenses): Collection
    {
        $days = $from->diffInDays($to) + 1;
        $mode = $days <= 31 ? 'day' : ($days <= 180 ? 'week' : 'month');
        $points = collect();
        $cursor = $from->copy()->startOfDay();

        while ($cursor->lte($to)) {
            $start = $cursor->copy();
            $end = match ($mode) {
                'day' => $start->copy()->endOfDay(),
                'week' => $start->copy()->addDays(6)->endOfDay(),
                default => $start->copy()->endOfMonth(),
            };
            if ($end->gt($to)) $end = $to->copy();

            $income = (float) $payments->filter(fn (Payment $payment) => $payment->paid_at->betweenIncluded($start, $end))->sum('amount');
            $expense = (float) $expenses->filter(fn (Expense $item) => $item->spent_at->betweenIncluded($start, $end))->sum('amount');
            $label = match ($mode) {
                'day' => $start->translatedFormat('d M'),
                'week' => $start->translatedFormat('d M').'–'.$end->translatedFormat('d M'),
                default => $start->translatedFormat('M Y'),
            };
            $points->push(['label'=>$label, 'income'=>$income, 'expense'=>$expense, 'net'=>$income - $expense]);
            $cursor = $end->copy()->addDay()->startOfDay();
        }

        return $points;
    }

    private function percent(float $value, float $base): ?float
    {
        return $base != 0.0 ? round($value / $base * 100, 1) : null;
    }

    private function change(float $current, float $previous): ?float
    {
        if ($previous == 0.0) return $current == 0.0 ? 0 : null;

        return round(($current - $previous) / abs($previous) * 100, 1);
    }
}
