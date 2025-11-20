<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Models\SavingGoal;
use App\Models\Tanda;
use App\Models\CalendarEvent;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();
        $today = now();

        /**
         * 1) Ahorro total (metas donde es dueño o participante)
         */
        $goalsQuery = SavingGoal::where(function ($q) use ($user) {
            $q->where('user_id', $user->id)
              ->orWhereHas('participants', function ($qp) use ($user) {
                  $qp->where('user_id', $user->id);
              });
        });

        $totalSavings = (float) $goalsQuery->sum('current_amount');

        // Cambio mensual aproximado (lo dejamos simple por ahora: suma de current_amount actualizado este mes)
        $monthlyChange = (float) $goalsQuery
            ->whereMonth('updated_at', $today->month)
            ->whereYear('updated_at', $today->year)
            ->sum('current_amount');

        /**
         * 2) Recibos / pagos
         */
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

        /**
         * 3) Metas de ahorro (lista corta para el dashboard)
         */
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

        /**
         * 4) Tandas (🔥 aquí estaba el problema: organizer_id → user_id)
         */
        $activeTandasCount = Tanda::where(function ($q) use ($user) {
                $q->where('user_id', $user->id) // dueño de la tanda
                  ->orWhereHas('members', function ($qp) use ($user) {
                      $qp->where('user_id', $user->id); // participa en la tanda
                  });
            })
            ->where('status', 'active')
            ->count();

        $nextTandaPayment = Tanda::where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhereHas('members', function ($qp) use ($user) {
                      $qp->where('user_id', $user->id);
                  });
            })
            ->where('status', 'active')
            ->whereDate('next_payment_date', '>=', $today->toDateString())
            ->orderBy('next_payment_date', 'asc')
            ->first(['id', 'name', 'next_payment_date', 'contribution_amount']);

        /**
         * 5) Eventos del calendario (próximos 7 días)
         */
        $upcomingEvents = CalendarEvent::where('user_id', $user->id)
            ->whereBetween('date', [
                $today->toDateString(),
                $today->copy()->addDays(7)->toDateString(),
            ])
            ->orderBy('date', 'asc')
            ->take(5)
            ->get(['id', 'title', 'date', 'type', 'amount']);

        return response()->json([
            'savings' => [
                'total'          => $totalSavings,
                'monthly_change' => $monthlyChange,
            ],
            'bills' => [
                'pending_count' => $pendingBillsCount,
                'paid_this_month' => $paidBillsThisMonth,
                'next' => $nextBills,
            ],
            'goals' => $goals,
            'tandas' => [
                'active_count' => $activeTandasCount,
                'next_payment' => $nextTandaPayment,
            ],
            'calendar' => [
                'upcoming_events' => $upcomingEvents,
            ],
        ]);
    }
}
