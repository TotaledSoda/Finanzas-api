<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class BillController extends Controller
{
    /**
     * Lista los recibos del usuario autenticado.
     * Filtros:
     *  - ?status=pending|paid|all|overdue
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $status = $request->get('status', 'pending'); // default: pendientes

        $query = Bill::where('user_id', $user->id);

        if ($status === 'pending') {
            $query->where('status', 'pending');
        } elseif ($status === 'paid') {
            $query->where('status', 'paid');
        } elseif ($status === 'overdue') {
            $query->where('status', 'pending')
                  ->whereDate('due_date', '<', Carbon::today());
        } elseif ($status === 'all') {
            // no se filtra por status
        } else {
            // valor raro → lo tratamos como pending
            $query->where('status', 'pending');
        }

        // Orden: primero más urgentes
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
        ]);

        $data['user_id'] = $request->user()->id;
        $data['status'] = 'pending';
        $data['auto_debit'] = $data['auto_debit'] ?? false;

        $bill = Bill::create($data);

        return response()->json(
            $this->transformBill($bill),
            201
        );
    }

    /**
     * Muestra un recibo específico.
     */
    public function show(Request $request, $id)
    {
        $bill = Bill::where('user_id', $request->user()->id)
            ->findOrFail($id);

        return response()->json($this->transformBill($bill));
    }

    /**
     * Actualiza un recibo.
     * Se puede marcar como pagado con:
     *  - status = "paid"
     *  - o mandando paid_at
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

        $bill->fill($data);

        // Lógica de pago
        if (array_key_exists('status', $data) && $data['status'] === 'paid' && ! $bill->paid_at) {
            $bill->paid_at = now();
        }

        if (array_key_exists('paid_at', $data) && $data['paid_at'] && $bill->status !== 'paid') {
            $bill->status = 'paid';
        }

        $bill->save();

        return response()->json($this->transformBill($bill));
    }

    /**
     * Elimina un recibo.
     */
    public function destroy(Request $request, $id)
    {
        $bill = Bill::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $bill->delete();

        return response()->json([
            'message' => 'Recibo eliminado correctamente.',
        ]);
    }

    /**
     * Formato de respuesta para la app.
     */
    protected function transformBill(Bill $bill): array
    {
        $days = $bill->days_until_due;

        // Texto amigable tipo "Vence en 3 días" / "Vencido" / "Pagado"
        if ($bill->is_paid) {
            $status_text = 'Pagado';
        } elseif ($bill->is_overdue) {
            $status_text = 'Vencido';
        } elseif (! is_null($days)) {
            if ($days === 0) {
                $status_text = 'Vence hoy';
            } elseif ($days === 1) {
                $status_text = 'Vence en 1 día';
            } elseif ($days > 1) {
                $status_text = "Vence en {$days} días";
            } else {
                // negativo pero no lo marcamos como overdue por alguna razón
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
            'category'      => $bill->category,    // para iconos: bolt, house, wifi, etc.
            'auto_debit'    => $bill->auto_debit,

            'is_paid'       => $bill->is_paid,
            'is_overdue'    => $bill->is_overdue,
            'days_until_due'=> $days,
            'status_text'   => $status_text,       // "Vencido", "Vence en 3 días", "Pagado"

            'paid_at'       => $bill->paid_at?->toAtomString(),
            'created_at'    => $bill->created_at?->toAtomString(),
            'updated_at'    => $bill->updated_at?->toAtomString(),
        ];
    }
}
