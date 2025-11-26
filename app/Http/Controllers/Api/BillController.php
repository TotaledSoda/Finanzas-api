<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Models\WeeklyIncome;
use App\Models\Expense;
use App\Models\FinancialEvent;

class BillController extends Controller
{
    /**
     * Lista los recibos del usuario autenticado.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $status = $request->get('status', 'pending');

        $query = Bill::where('user_id', $user->id);

        if ($status === 'pending') {
            $query->where('status', 'pending');
        } elseif ($status === 'paid') {
            $query->where('status', 'paid');
        } elseif ($status === 'overdue') {
            $query->where('status', 'pending')
                  ->whereDate('due_date', '<', Carbon::today());
        } elseif ($status !== 'all') {
            $query->where('status', 'pending');
        }

        $bills = $query
            ->orderBy('due_date', 'asc')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (Bill $bill) => $this->transformBill($bill));

        return response()->json($bills);
    }

    /**
     * Crea un nuevo recibo.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'provider'    => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'amount'      => ['required', 'numeric', 'min:0'],
            'due_date'    => ['required', 'date'],
            'category'    => ['nullable', 'string', 'max:50'],
            'auto_debit'  => ['nullable', 'boolean'],
            'status'      => ['sometimes', 'string', 'in:pending,paid,cancelled'],
            'paid_at'     => ['sometimes', 'nullable', 'date'],
        ]);

        $data['user_id'] = $request->user()->id;
        $data['auto_debit'] = $data['auto_debit'] ?? false;

        if (!isset($data['status'])) {
            $data['status'] = 'pending';
        }

        if ($data['status'] === 'paid' && empty($data['paid_at'])) {
            $data['paid_at'] = now();
        }

        $bill = Bill::create($data);

        // Crear evento en el calendario
        $this->createOrUpdateCalendarEvent($bill);

        // Registrar expense si nace como pagado
        if ($bill->is_paid) {
            $this->registerBillExpense($bill);
        }

        return response()->json(
            $this->transformBill($bill),
            201
        );
    }

    /**
     * Muestra un recibo.
     */
    public function show(Request $request, $id)
    {
        $bill = Bill::where('user_id', $request->user()->id)
            ->findOrFail($id);

        return response()->json($this->transformBill($bill));
    }

    /**
     * Actualiza un recibo.
     */
    public function update(Request $request, $id)
    {
        $bill = Bill::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $data = $request->validate([
            'name'        => ['sometimes', 'string', 'max:255'],
            'provider'    => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'amount'      => ['sometimes', 'numeric', 'min:0'],
            'due_date'    => ['sometimes', 'date'],
            'category'    => ['sometimes', 'nullable', 'string', 'max:50'],
            'auto_debit'  => ['sometimes', 'boolean'],
            'status'      => ['sometimes', 'string', 'in:pending,paid,cancelled'],
            'paid_at'     => ['sometimes', 'nullable', 'date'],
        ]);

        $wasPaid = $bill->is_paid;

        $bill->fill($data);

        if (array_key_exists('status', $data) && $data['status'] === 'paid' && !$bill->paid_at) {
            $bill->paid_at = now();
        }

        if (array_key_exists('paid_at', $data) && $data['paid_at'] && $bill->status !== 'paid') {
            $bill->status = 'paid';
        }

        $bill->save();

        // Actualizar evento del calendario
        $this->createOrUpdateCalendarEvent($bill);

        if ($bill->is_paid && !$wasPaid) {
            $this->registerBillExpense($bill);
        }

        return response()->json($this->transformBill($bill));
    }

    /**
     * Elimina un recibo.
     */
    public function destroy(Request $request, $id)
    {
        $bill = Bill::where('user_id', $request->user()->id)
            ->findOrFail($id);

        // Borrar evento en el calendario
        FinancialEvent::where('eventable_type', Bill::class)
            ->where('eventable_id', $bill->id)
            ->delete();

        $bill->delete();

        return response()->json(['message' => 'Recibo eliminado correctamente.']);
    }

    /**
     * Creación o actualización del evento en el calendario.
     */
    protected function createOrUpdateCalendarEvent(Bill $bill)
    {
        FinancialEvent::updateOrCreate(
            [
                'eventable_type' => Bill::class,
                'eventable_id'   => $bill->id,
            ],
            [
                'user_id'  => $bill->user_id,
                'title'    => $bill->name,
                'date'     => $bill->due_date,
                'amount'   => $bill->amount,
                'category' => 'bill',
                'status'   => $bill->status,
            ]
        );
    }

    /**
     * Registrar un Expense cuando el bill se paga.
     */
    protected function registerBillExpense(Bill $bill): void
    {
        if (!$bill->user_id || !$bill->amount) return;

        $userId = $bill->user_id;

        $exists = Expense::where('user_id', $userId)
            ->where('type', 'bill')
            ->where('source_id', $bill->id)
            ->exists();

        if ($exists) return;

        $paymentDate = $bill->paid_at
            ? Carbon::parse($bill->paid_at)
            : Carbon::today();

        $weekStart = (clone $paymentDate)->startOfWeek(Carbon::MONDAY);
        $weekEnd   = (clone $paymentDate)->endOfWeek(Carbon::SUNDAY);

        $weeklyIncome = WeeklyIncome::firstOrCreate(
            [
                'user_id'    => $userId,
                'week_start' => $weekStart->toDateString(),
                'week_end'   => $weekEnd->toDateString(),
            ],
            [
                'amount'   => 0,
                'spent'    => 0,
                'saved'    => 0,
                'leftover' => 0,
            ]
        );

        Expense::create([
            'user_id'          => $userId,
            'weekly_income_id' => $weeklyIncome->id,
            'date'             => $paymentDate->toDateString(),
            'amount'           => $bill->amount,
            'type'             => 'bill',
            'source_id'        => $bill->id,
            'description'      => $bill->name,
        ]);
    }

    /**
     * Formato de salida.
     */
    protected function transformBill(Bill $bill): array
    {
        $days = $bill->days_until_due;

        if ($bill->is_paid) {
            $status_text = 'Pagado';
        } elseif ($bill->is_overdue) {
            $status_text = 'Vencido';
        } elseif (!is_null($days)) {
            if ($days === 0) {
                $status_text = 'Vence hoy';
            } elseif ($days === 1) {
                $status_text = 'Vence en 1 día';
            } elseif ($days > 1) {
                $status_text = "Vence en {$days} días";
            } else {
                $status_text = 'Vencido';
            }
        } else {
            $status_text = null;
        }

        return [
            'id'            => $bill->id,
            'name'          => $bill->name,
            'provider'      => $bill->provider,
            'description'   => $bill->description,
            'amount'        => (float) $bill->amount,
            'due_date'      => optional($bill->due_date)->toDateString(),
            'status'        => $bill->status,
            'category'      => $bill->category,
            'auto_debit'    => $bill->auto_debit,
            'is_paid'       => $bill->is_paid,
            'is_overdue'    => $bill->is_overdue,
            'days_until_due'=> $days,
            'status_text'   => $status_text,
            'paid_at'       => $bill->paid_at?->toAtomString(),
            'created_at'    => $bill->created_at?->toAtomString(),
            'updated_at'    => $bill->updated_at?->toAtomString(),
        ];
    }
}
