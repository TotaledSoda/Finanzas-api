<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Models\SavingGoal;
use App\Models\Tanda;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    /**
     * Devuelve el resumen del dashboard del usuario autenticado.
     */
    public function show(Request $request)
    {
        $user = $request->user();

        $today       = Carbon::today();
        $startMonth  = $today->copy()->startOfMonth();
        $endMonth    = $today->copy()->endOfMonth();
        $startPrev   = $startMonth->copy()->subMonth();
        $endPrev     = $startMonth->copy()->subDay();

        // 1) Resumen de ahorro
        $totalSavings = SavingGoal::where('user_id', $user->id)
            ->where('status', '!=', 'cancelled')
            ->sum('current_amount');

        // Aproximación: cuánto "se movió" este mes vs mes anterior
        $currentMonthSavingsSnapshot = SavingGoal::where('user_id', $user->id)
            ->whereBetween('updated_at', [$startMonth, $endMonth])
            ->sum('current_amount');

        $previousMonthSavingsSnapshot = SavingGoal::where('user_id', $user->id)
            ->whereBetween('updated_at', [$startPrev, $endPrev])
            ->sum('current_amount');

        $monthlyChangePercent = null;
        if ($previousMonthSavingsSnapshot > 0) {
            $diff = $currentMonthSavingsSnapshot - $previousMonthSavingsSnapshot;
            $monthlyChangePercent = round(($diff / $previousMonthSavingsSnapshot) * 100, 1);
        }

        $activeGoalsCount = SavingGoal::where('user_id', $user->id)
            ->where('status', 'active')
            ->count();

        $activeTandasCount = Tanda::where(function ($q) use ($user) {
                $q->where('organizer_id', $user->id)
                  ->orWhereHas('members', function ($q2) use ($user) {
                      $q2->where('user_id', $user->id);
                  });
            })
            ->where('status', 'active')
            ->count();

        $pendingBillsTotal = Bill::where('user_id', $user->id)
            ->where('status', 'pending')
            ->sum('amount');

        // 2) Metas destacadas (para "Mis Ahorros" en el dashboard)
        $topGoals = SavingGoal::where('user_id', $user->id)
            ->whereIn('status', ['active', 'completed'])
            ->orderByRaw('CASE WHEN status = "active" THEN 0 ELSE 1 END') // activas primero
            ->orderByDesc('current_amount')
            ->limit(3)
            ->get()
            ->map(function (SavingGoal $goal) {
                $percent = $goal->progress_percent;

                return [
                    'id'              => $goal->id,
                    'name'            => $goal->name,
                    'current_amount'  => (float) $goal->current_amount,
                    'target_amount'   => (float) $goal->target_amount,
                    'progress_percent'=> $percent,
                    'status'          => $goal->status,
                    'deadline'        => optional($goal->deadline)->toDateString(),
                    'category'        => $goal->category,
                ];
            })
            ->values();

        // 3) Próximos vencimientos (bills)
        $upcomingBills = Bill::where('user_id', $user->id)
            ->where('status', 'pending')
            ->whereDate('due_date', '>=', $today->toDateString())
            ->orderBy('due_date', 'asc')
            ->limit(3)
            ->get()
            ->map(function (Bill $bill) {
                $days = $bill->days_until_due;

                if ($bill->is_overdue) {
                    $label = 'Vencido';
                } elseif ($days === 0) {
                    $label = 'Vence hoy';
                } elseif ($days === 1) {
                    $label = 'Vence en 1 día';
                } elseif ($days > 1) {
                    $label = "Vence en {$days} días";
                } else {
                    $label = 'Vencido';
                }

                return [
                    'id'         => $bill->id,
                    'name'       => $bill->name,
                    'amount'     => (float) $bill->amount,
                    'due_date'   => optional($bill->due_date)->toDateString(),
                    'status'     => $bill->status,
                    'label'      => $label,
                    'category'   => $bill->category,
                    'is_overdue' => $bill->is_overdue,
                ];
            })
            ->values();

        // 4) Próximas tandas (para mostrar algo tipo "Próximo pago/cobro")
        $upcomingTandas = Tanda::where(function ($q) use ($user) {
                $q->where('organizer_id', $user->id)
                  ->orWhereHas('members', function ($q2) use ($user) {
                      $q2->where('user_id', $user->id);
                  });
            })
            ->whereIn('status', ['active', 'upcoming'])
            ->whereNotNull('next_date')
            ->orderBy('next_date', 'asc')
            ->limit(3)
            ->get()
            ->map(function (Tanda $tanda) use ($user) {
                $userIsOrganizer = $tanda->organizer_id === $user->id;

                return [
                    'id'                  => $tanda->id,
                    'name'                => $tanda->name,
                    'role'                => $userIsOrganizer ? 'organizer' : 'participant',
                    'next_date'           => optional($tanda->next_date)->toDateString(),
                    'contribution_amount' => (float) $tanda->contribution_amount,
                    'current_round'       => $tanda->current_round,
                    'total_rounds'        => $tanda->total_rounds,
                    'progress_percent'    => $tanda->progress_percent,
                    'status'              => $tanda->status,
                ];
            })
            ->values();

        // 5) Armar respuesta final
        return response()->json([
            'summary' => [
                'total_savings'         => (float) $totalSavings,
                'monthly_change_percent'=> $monthlyChangePercent, // puede ser null si no hay datos previos
                'active_goals_count'    => $activeGoalsCount,
                'active_tandas_count'   => $activeTandasCount,
                'pending_bills_total'   => (float) $pendingBillsTotal,
            ],
            'goals' => $topGoals,
            'upcoming_bills'  => $upcomingBills,
            'upcoming_tandas' => $upcomingTandas,
        ]);
    }
}
