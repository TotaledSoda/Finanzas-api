<?php 

use App\Models\WeeklyIncome;
use App\Models\Expense;
use Illuminate\Support\Carbon;

 public function storeWeeklyIncome(Request $request)
{
    $user = $request->user();

    $data = $request->validate([
        'amount' => ['required', 'numeric', 'min:0'],
    ]);

    $today = now();
    // ejemplo: semana de lunes a domingo
    $weekStart = $today->copy()->startOfWeek(Carbon::MONDAY);
    $weekEnd   = $today->copy()->endOfWeek(Carbon::SUNDAY);

    // Opcional: cerrar semana anterior (calcular sobrante y mandarlo a ahorro)
    $previous = WeeklyIncome::where('user_id', $user->id)
        ->where('week_end', '<', $weekStart->toDateString())
        ->orderByDesc('week_start')
        ->first();

    if ($previous && $previous->leftover == 0 && $previous->spent == 0) {
        // rellenar resumen de la semana anterior
        $totalSpent = $previous->expenses()->sum('amount');
        $previous->spent = $totalSpent;
        $previous->leftover = max(0, $previous->amount - $totalSpent);

        // Aquí podrías mandar el leftover a una meta de ahorro
        // por ejemplo, a un SavingGoal "Ahorro general"
        // (solo dejo el comentario para no romper nada)
        $previous->save();
    }

    // Crear / actualizar sueldo de la semana actual
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
