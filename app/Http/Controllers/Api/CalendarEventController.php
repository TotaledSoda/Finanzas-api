<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Models\SavingGoal;
use App\Models\Tanda;
use App\Models\CalendarEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CalendarEventController extends Controller
{
    /**
     * Devuelve todos los eventos financieros del mes.
     * Query param:
     *   - month=YYYY-MM (ej: 2024-10). Si no se envía, toma el mes actual.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $monthParam = $request->get('month'); // '2024-10'
        if ($monthParam) {
            try {
                [$year, $month] = explode('-', $monthParam);
                $start = Carbon::createFromDate((int) $year, (int) $month, 1)->startOfDay();
            } catch (\Throwable $e) {
                $start = Carbon::now()->startOfMonth();
            }
        } else {
            $start = Carbon::now()->startOfMonth();
        }

        $end = (clone $start)->endOfMonth();

        // 1) Recibos (bills)
        $bills = Bill::where('user_id', $user->id)
            ->whereBetween('due_date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->map(fn (Bill $bill) => [
                'date'        => $bill->due_date?->toDateString(),
                'type'        => 'bill',
                'source'      => 'bill',
                'source_id'   => $bill->id,
                'title'       => $bill->name,
                'subtitle'    => $bill->status_text,
                'amount'      => (float) $bill->amount,
                'category'    => $bill->category ?? 'bill',
            ]);

        // 2) Metas de ahorro (saving_goals) por fecha límite
        $goals = SavingGoal::where('user_id', $user->id)
            ->whereNotNull('deadline')
            ->whereBetween('deadline', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->map(fn (SavingGoal $goal) => [
                'date'        => $goal->deadline?->toDateString(),
                'type'        => 'goal',
                'source'      => 'goal',
                'source_id'   => $goal->id,
                'title'       => $goal->name,
                'subtitle'    => 'Meta de ahorro',
                'amount'      => (float) $goal->target_amount,
                'category'    => $goal->category ?? 'goal',
            ]);

        // 3) Tandas usando next_payment_date
        $tandas = Tanda::where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhereHas('participants', function ($q2) use ($user) {
                      $q2->where('user_id', $user->id);
                  });
            })
            ->whereNotNull('next_payment_date')
            ->whereBetween('next_payment_date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->map(fn (Tanda $tanda) => [
                'date'        => $tanda->next_payment_date?->toDateString(),
                'type'        => 'tanda',
                'source'      => 'tanda',
                'source_id'   => $tanda->id,
                'title'       => $tanda->name,
                'subtitle'    => 'Tanda (' . $tanda->current_round . '/' . $tanda->rounds_total . ')',
                'amount'      => (float) $tanda->contribution_amount,
                'category'    => 'tanda',
            ]);

        // 4) Eventos manuales
        $manualEvents = CalendarEvent::where('user_id', $user->id)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->map(fn (CalendarEvent $event) => [
                'date'        => $event->date?->toDateString(),
                'type'        => 'custom',
                'source'      => 'calendar_event',
                'source_id'   => $event->id,
                'title'       => $event->title,
                'subtitle'    => $event->description,
                'amount'      => $event->amount ? (float) $event->amount : null,
                'category'    => $event->category ?? 'custom',
            ]);

        $events = $bills
            ->merge($goals)
            ->merge($tandas)
            ->merge($manualEvents)
            ->sortBy('date')
            ->values()
            ->all();

        return response()->json([
            'month'  => $start->format('Y-m'),
            'start'  => $start->toDateString(),
            'end'    => $end->toDateString(),
            'events' => $events,
        ]);
    }
}
