<?php

namespace App\Services;

use App\Models\{Expense, ExpenseCategory, Payment};
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LedgerTableService
{
    public const PAGE_SIZES = [25, 50, 100, 250, 500, 1000];

    public function paymentFilters(Request $request): array
    {
        return [
            'search'=>$this->search($request->input('payment_search')),
            'from'=>$this->date($request->input('payment_from')),
            'to'=>$this->date($request->input('payment_to')),
            'min'=>$this->amount($request->input('payment_min')),
            'max'=>$this->amount($request->input('payment_max')),
            'method'=>$this->choice($request->input('payment_method'), ['Transfer','Cash','QRIS']),
            'cycle'=>$this->choice($request->input('payment_cycle'), ['DAILY','WEEKLY','MONTHLY']),
            'kind'=>$this->choice($request->input('payment_kind'), ['REGULAR','HISTORICAL']),
            'source'=>$this->choice($request->input('payment_source'), ['MANUAL','IMPORT']),
            'per_page'=>$this->pageSize($request->input('payment_per_page')),
        ];
    }

    public function expenseFilters(Request $request): array
    {
        return [
            'search'=>$this->search($request->input('expense_search')),
            'from'=>$this->date($request->input('expense_from')),
            'to'=>$this->date($request->input('expense_to')),
            'min'=>$this->amount($request->input('expense_min')),
            'max'=>$this->amount($request->input('expense_max')),
            'category'=>$this->search($request->input('expense_category'), 60),
            'cost_type'=>$this->choice($request->input('expense_cost_type'), ['DIRECT','OPERATING']),
            'cost_behavior'=>$this->choice($request->input('expense_cost_behavior'), ['VARIABLE','FIXED']),
            'source'=>$this->choice($request->input('expense_source'), ['MANUAL','IMPORT']),
            'per_page'=>$this->pageSize($request->input('expense_per_page')),
        ];
    }

    public function payments(array $filters): array
    {
        $query = Payment::query()->with(['tenant.room', 'recorder']);

        if ($filters['search']) {
            $like = $this->like($filters['search']);
            $query->where(function ($query) use ($like) {
                $query->where('period', 'ilike', $like)
                    ->orWhereHas('tenant', function ($tenant) use ($like) {
                        $tenant->where('name', 'ilike', $like)
                            ->orWhereHas('room', fn ($room) => $room->where('number', 'ilike', $like));
                    })
                    ->orWhereHas('recorder', fn ($user) => $user->where('name', 'ilike', $like));
            });
        }
        $this->dateRange($query, 'paid_at', $filters['from'], $filters['to']);
        $this->amountRange($query, $filters['min'], $filters['max']);
        if ($filters['method']) $query->where('method', $filters['method']);
        if ($filters['cycle']) $query->where('billing_cycle', $filters['cycle']);
        if ($filters['kind']) $query->where('is_historical', $filters['kind'] === 'HISTORICAL');
        if ($filters['source']) {
            $filters['source'] === 'IMPORT'
                ? $query->whereNotNull('import_batch_id')
                : $query->whereNull('import_batch_id');
        }

        $totalAmount = (float) (clone $query)->sum('amount');
        $paginator = $query->orderByDesc('paid_at')->orderByDesc('id')
            ->paginate($filters['per_page'], ['*'], 'payment_page')->withQueryString();

        return [$paginator, $totalAmount];
    }

    public function expenses(array $filters): array
    {
        $query = Expense::query()->with(['recorder', 'maintenance']);

        if ($filters['search']) {
            $like = $this->like($filters['search']);
            $query->where(function ($query) use ($like) {
                $query->where('title', 'ilike', $like)
                    ->orWhere('notes', 'ilike', $like)
                    ->orWhere('category', 'ilike', $like)
                    ->orWhereHas('recorder', fn ($user) => $user->where('name', 'ilike', $like));
            });
        }
        $this->dateRange($query, 'spent_at', $filters['from'], $filters['to']);
        $this->amountRange($query, $filters['min'], $filters['max']);
        if ($filters['category']) $query->where('category', $filters['category']);
        if ($filters['cost_type']) $query->whereIn('category', ExpenseCategory::where('cost_type', $filters['cost_type'])->select('name'));
        if ($filters['cost_behavior']) $query->whereIn('category', ExpenseCategory::where('cost_behavior', $filters['cost_behavior'])->select('name'));
        if ($filters['source']) {
            $filters['source'] === 'IMPORT'
                ? $query->whereNotNull('import_batch_id')
                : $query->whereNull('import_batch_id');
        }

        $totalAmount = (float) (clone $query)->sum('amount');
        $paginator = $query->orderByDesc('spent_at')->orderByDesc('id')
            ->paginate($filters['per_page'], ['*'], 'expense_page')->withQueryString();

        return [$paginator, $totalAmount];
    }

    private function dateRange($query, string $column, ?string $from, ?string $to): void
    {
        if ($from && $to && $from > $to) [$from, $to] = [$to, $from];
        if ($from) $query->whereDate($column, '>=', $from);
        if ($to) $query->whereDate($column, '<=', $to);
    }

    private function amountRange($query, ?int $min, ?int $max): void
    {
        if ($min !== null && $max !== null && $min > $max) [$min, $max] = [$max, $min];
        if ($min !== null) $query->where('amount', '>=', $min);
        if ($max !== null) $query->where('amount', '<=', $max);
    }

    private function search(mixed $value, int $limit = 100): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : Str::limit($value, $limit, '');
    }

    private function date(mixed $value): ?string
    {
        $value = trim((string) $value);
        return Carbon::hasFormat($value, 'Y-m-d') ? $value : null;
    }

    private function amount(mixed $value): ?int
    {
        $digits = preg_replace('/\D+/', '', (string) $value);
        return $digits === '' ? null : max(0, (int) $digits);
    }

    private function choice(mixed $value, array $allowed): ?string
    {
        $value = trim((string) $value);
        return in_array($value, $allowed, true) ? $value : null;
    }

    private function pageSize(mixed $value): int
    {
        $value = (int) $value;
        return in_array($value, self::PAGE_SIZES, true) ? $value : self::PAGE_SIZES[0];
    }

    private function like(string $value): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value).'%';
    }
}
