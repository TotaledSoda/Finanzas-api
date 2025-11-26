<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Models\SavingGoal;
use App\Models\Tanda;
use App\Models\Expense;
use App\Models\CalendarEvent; // eventos manuales, si los usas
use Illuminate\Http\Request;
use Carbon\Carbon;

class CalendarController extends Controller
{
    /**
     * GET /api/calendar?start_date=2025-11-01&end_date=2025-11-30
     *
     * Devuelve eventos combinados de:
     * - Pagos (bills)
     * - Tandas
     * - Metas de ahorro
     * - Gastos diarios (sumados por día)
     * - Eventos manuales (CalendarEvent)
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // 1) Rango de fechas
        $start = $request->query('start_date')
            ? Carbon::parse($request->query('start_date'))->startOfDay()
            : now()->startOfMonth();

        $end = $request->query('end_date')
            ? Carbon::parse($request->query('end_date'))->endOfDay()
            : now()->endOfMonth();

        // Aseguramos que start <= end
        if ($start->gt($end)) {
            [$start, $end] = [$end, $start];
        }

        /**
         * 2) Pagos (Bills)
         *    - Se registran en el calendario con la fecha de vencimiento (due_date)
         */
        $bills = Bill::where('user_id', $user->id)
            ->whereBetween('due_date', [$start->toDateString(), $end->toDateString()])
            ->get();

        $billEvents = $bills->map(function (Bill $bill) {
            return [
                'source'    => 'bill',
                'source_id' => $bill->id,
                'date'      => optional($bill->due_date)->toDateString(),
                'title'     => $bill->name,
                'amount'    => (float) $bill->amount,
                'status'    => $bill->status, // pending / paid
                'meta'      => [
                    'type' => 'payment',
                ],
            ];
        });

        /**
         * 3) Tandas
         *    - Mostramos el próximo pago de cada tanda en el rango
         *    - Podrías extender esto calculando TODAS las fechas futuras según frecuencia
         */
        $tandas = Tanda::where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhereHas('members', function ($qp) use ($user) {
                      $qp->where('user_id', $user->id);
                  });
            })
            ->whereNotNull('next_payment_date')
            ->whereBetween('next_payment_date', [$start->toDateString(), $end->toDateString()])
            ->get();

        $tandaEvents = $tandas->map(function (Tanda $tanda) {
            return [
                'source'    => 'tanda',
                'source_id' => $tanda->id,
                'date'      => optional($tanda->next_payment_date)->toDateString(),
                'title'     => 'Tanda: '.$tanda->name,
                'amount'    => (float) $tanda->contribution_amount,
                'status'    => $tanda->status, // active, finished, cancelled
                'meta'      => [
                    'frequency' => $tanda->frequency,
                    'rounds_total' => $tanda->rounds_total,
                    'current_round' => $tanda->current_round,
                ],
            ];
        });

        /**
         * 4) Metas de ahorro
         *    - Se registran en el calendario con la fecha límite (deadline)
         */
        $goals = SavingGoal::where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhereHas('participants', function ($qp) use ($user) {
                      $qp->where('user_id', $user->id);
                  });
            })
            ->whereNotNull('deadline')
            ->whereBetween('deadline', [$start->toDateString(), $end->toDateString()])
            ->get();

        $goalEvents = $goals->map(function (SavingGoal $goal) {
            return [
                'source'    => 'saving_goal',
                'source_id' => $goal->id,
                'date'      => optional($goal->deadline)->toDateString(),
                'title'     => 'Meta: '.$goal->name,
                'amount'    => (float) $goal->target_amount,
                'status'    => $goal->status,
                'meta'      => [
                    'current_amount'   => (float) $goal->current_amount,
                    'progress_percent' => $goal->progress_percent,
                    'is_group'         => (bool) $goal->is_group,
                ],
            ];
        });

        /**
         * 5) Eventos manuales (CalendarEvent)
         *    - Por si quieres crear eventos a mano (cumpleaños, recordatorios, etc.)
         */
        $manualEvents = CalendarEvent::where('user_id', $user->id)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->map(function (CalendarEvent $ev) {
                return [
                    'source'    => 'manual',
                    'source_id' => $ev->id,
                    'date'      => optional($ev->date)->toDateString(),
                    'title'     => $ev->title,
                    'amount'    => (float) ($ev->amount ?? 0),
                    'status'    => $ev->type, // por ejemplo: reminder, note, etc.
                    'meta'      => [
                        'raw_type' => $ev->type,
                    ],
                ];
            });

        /**
         * 6) Gastos diarios (Expense)
         *    - Aquí no es "evento" individual, sino sumatoria por día.
         *    - Esto sirve para pintar en el calendario cuánto se gastó por día.
         */
        $dailyExpenses = Expense::where('user_id', $user->id)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
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

        /**
         * 7) Mezclamos todos los eventos en un solo array.
         */
        $events = $billEvents
            ->merge($tandaEvents)
            ->merge($goalEvents)
            ->merge($manualEvents)
            ->sortBy('date')
            ->values()
            ->all();

        return response()->json([
            'range' => [
                'start' => $start->toDateString(),
                'end'   => $end->toDateString(),
            ],
            'events'         => $events,
            'daily_expenses' => $dailyExpenses,
        ]);
    }
}
