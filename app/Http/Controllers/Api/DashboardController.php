<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Models\SavingGoal;
use App\Models\Tanda;
use App\Models\CalendarEvent;
use App\Models\WeeklyIncome;
use App\Models\Expense;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * GET /api/dashboard
     */
    public function show(Request $request)
    {
        $user = $request->user();
        $today = now();

        // 1) Ahorro total
        $goalsQuery = SavingGoal::where(function ($q) use ($user) {
            $q->where('user_id', $user->id)
              ->orWhereHas('participants', function ($qp) use ($user) {
                  $qp->where('user_id', $user->id);
              });
        });

        $totalSavings = (float) $goalsQuery->sum('current_amount');

        $monthlyChange = (float) (clone $goalsQuery)
            ->whereMonth('updated_at', $today->month)
            ->whereYear('updated_at', $today->year)
            ->sum('current_amount');

        // 2) Recibos
        $pendingBillsCount = Bill::where('user_id', $user->id)
            ->where('status', 'pending')
            ->count();

        $paidBillsThisMonth = Bill::where('user_id', $user->id)
            ->where('status', 'paid')
            ->whereMonth('paid_at', $today->month)
            ->whereYear('paid_at', $today->year)
            ->sum('amount');

        $nextBills = Bill::where('user_id', $user->id)
            ->where('status', 'pending')
            ->whereDate('due_date', '>=', $today->toDateString())
            ->orderBy('due_date', 'asc')
            ->take(3)
            ->get(['id', 'name', 'amount', 'due_date', 'status']);

        // 3) Metas de ahorro resumidas
        $goals = $goalsQuery
            ->orderBy('deadline', 'asc')
            ->take(3)
            ->get()
            ->map(function (SavingGoal $goal) {
                return [
                    'id'               => $goal->id,
                    'name'             => $goal->name,
                    'target_amount'    => (float) $goal->target_amount,
                    'current_amount'   => (float) $goal->current_amount,
                    'progress_percent' => $goal->progress_percent,
                    'deadline'         => optional($goal->deadline)->toDateString(),
                    'status'           => $goal->status,
                    'is_group'         => $goal->is_group,
                ];
            });

        // 4) Tandas
        $activeTandasCount = Tanda::where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhereHas('participants', function ($qp) use ($user) {
                      $qp->where('user_id', $user->id);
                  });
            })
            ->where('status', 'active')
            ->count();

        $nextTandaPayment = Tanda::where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhereHas('participants', function ($qp) use ($user) {
                      $qp->where('user_id', $user->id);
                  });
            })
            ->where('status', 'active')
            ->whereDate('next_payment_date', '>=', $today->toDateString())
            ->orderBy('next_payment_date', 'asc')
            ->first(['id', 'name', 'next_payment_date', 'contribution_amount']);

        // 5) Eventos del calendario próximos 7 días
        $upcomingEvents = CalendarEvent::where('user_id', $user->id)
            ->whereBetween('date', [
                $today->toDateString(),
                $today->copy()->addDays(7)->toDateString(),
            ])
            ->orderBy('date', 'asc')
            ->take(5)
            ->get(['id', 'title', 'date', 'type', 'amount']);

        // 6) Sueldo semanal actual y gastos de la semana
        $weekStart = $today->copy()->startOfWeek(Carbon::MONDAY);
        $weekEnd   = $today->copy()->endOfWeek(Carbon::SUNDAY);

        $currentIncome = WeeklyIncome::where('user_id', $user->id)
            ->where('week_start', $weekStart->toDateString())
            ->where('week_end', $weekEnd->toDateString())
            ->first();

        $weeklyIncomeAmount = $currentIncome?->amount ?? 0;

        $spentThisWeek = Expense::where('user_id', $user->id)
            ->whereBetween('date', [
                $weekStart->toDateString(),
                $weekEnd->toDateString(),
            ])
            ->sum('amount');

        $availableThisWeek = max(0, $weeklyIncomeAmount - $spentThisWeek);

        // 7) Gastos por día del mes
        $monthStart = $today->copy()->startOfMonth();
        $monthEnd   = $today->copy()->endOfMonth();

        $dailyExpenses = Expense::where('user_id', $user->id)
            ->whereBetween('date', [
                $monthStart->toDateString(),
                $monthEnd->toDateString(),
            ])
            ->selectRaw('date, SUM(amount) as total')
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get()
            ->map(function ($row) {
                $date = Carbon::parse($row->date);
                return [
                    'date'  => $date->toDateString(),
                    'total' => (float) $row->total,
                ];
            });

        return response()->json([
            'savings' => [
                'total'          => $totalSavings,
                'monthly_change' => $monthlyChange,
            ],
            'bills' => [
                'pending_count'   => $pendingBillsCount,
                'paid_this_month' => $paidBillsThisMonth,
                'next'            => $nextBills,
            ],
            'goals' => $goals,
            'tandas' => [
                'active_count' => $activeTandasCount,
                'next_payment' => $nextTandaPayment,
            ],
            'calendar' => [
                'upcoming_events' => $upcomingEvents,
                'daily_expenses'  => $dailyExpenses,
            ],
            'income' => [
                'weekly_income'       => (float) $weeklyIncomeAmount,
                'spent_this_week'     => (float) $spentThisWeek,
                'available_this_week' => (float) $availableThisWeek,
            ],
        ]);
    }

    /**
     * POST /api/dashboard/weekly-income
     * Registrar / actualizar sueldo semanal
     */
    public function updateWeeklyIncome(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0'],
        ]);

        $today = now();
        $weekStart = $today->copy()->startOfWeek(Carbon::MONDAY);
        $weekEnd   = $today->copy()->endOfWeek(Carbon::SUNDAY);

        $income = WeeklyIncome::updateOrCreate(
            [
                'user_id'    => $user->id,
                'week_start' => $weekStart->toDateString(),
                'week_end'   => $weekEnd->toDateString(),
            ],
            [
                'amount' => $data['amount'],
            ]
        );

        return response()->json([
            'message' => 'Sueldo semanal registrado',
            'income'  => $income,
        ]);
    }
}
