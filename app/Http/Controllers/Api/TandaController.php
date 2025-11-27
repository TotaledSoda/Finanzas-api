<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tanda;
use App\Models\User;
use App\Models\TandaPayment;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TandaController extends Controller
{
    /**
     * Listar tandas donde el usuario es dueño o miembro.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $tandas = Tanda::with(['members', 'payments'])
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhereHas('members', function ($qp) use ($user) {
                      $qp->where('user_id', $user->id);
                  });
            })
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($tandas);
    }

    /**
     * Crear una nueva tanda.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name'                => ['required', 'string', 'max:255'],
            'description'         => ['nullable', 'string'],
            'contribution_amount' => ['required', 'numeric', 'min:0.01'],
            'num_members'         => ['required', 'integer', 'min:2'],
            'frequency'           => ['required', 'in:weekly,biweekly,monthly'],
            'start_date'          => ['required', 'date'],
        ]);

        $potAmount = $data['contribution_amount'] * $data['num_members'];

        $tanda = Tanda::create([
            'user_id'             => $user->id,
            'name'                => $data['name'],
            'description'         => $data['description'] ?? null,
            'contribution_amount' => $data['contribution_amount'],
            'num_members'         => $data['num_members'],
            'pot_amount'          => $potAmount,
            'frequency'           => $data['frequency'],
            'start_date'          => $data['start_date'],
            'current_round'       => 1,
            'status'              => 'active',
        ]);

        // El dueño entra como miembro turno 1
        $tanda->members()->syncWithoutDetaching([
            $user->id => [
                'turn_order'   => 1,
                'has_received' => false,
                'received_at'  => null,
            ],
        ]);

        $tanda->load(['members', 'payments']);

        return response()->json($tanda, 201);
    }

    /**
     * Agregar miembro a la tanda por correo.
     * (similar a saving goals)
     */
    public function addMember(Request $request, Tanda $tanda)
    {
        $userAuth = $request->user();

        // Solo el dueño puede agregar miembros
        if ($tanda->user_id !== $userAuth->id) {
            abort(403, 'No tienes permiso para modificar esta tanda.');
        }

        $data = $request->validate([
            'email'      => ['required', 'email'],
            'turn_order' => ['required', 'integer', 'min:1'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => ['No se encontró un usuario con ese correo.'],
            ]);
        }

        $tanda->members()->syncWithoutDetaching([
            $user->id => [
                'turn_order'   => $data['turn_order'],
                'has_received' => false,
                'received_at'  => null,
            ],
        ]);

        $tanda->load(['members', 'payments']);

        return response()->json([
            'ok'    => true,
            'tanda' => $tanda,
        ]);
    }

    /**
     * Registrar un pago de la tanda (aporte de un miembro).
     * Aquí luego podemos enganchar el ahorro total y el calendario.
     */
    public function registerPayment(Request $request, Tanda $tanda)
    {
        $user = $request->user();

        // podrías validar que sólo miembros de la tanda puedan pagar
        if (
            $tanda->user_id !== $user->id &&
            ! $tanda->members()->where('users.id', $user->id)->exists()
        ) {
            abort(403, 'No perteneces a esta tanda.');
        }

        $data = $request->validate([
            'amount'   => ['required', 'numeric', 'min:0.01'],
            'due_date' => ['nullable', 'date'],
            'paid_at'  => ['nullable', 'date'],
            'notes'    => ['nullable', 'string'],
        ]);

        $payment = new TandaPayment();
        $payment->tanda_id = $tanda->id;
        $payment->user_id  = $user->id;
        $payment->amount   = $data['amount'];
        $payment->due_date = $data['due_date'] ?? null;
        $payment->paid_at  = $data['paid_at'] ?? now();
        $payment->status   = 'paid';
        $payment->notes    = $data['notes'] ?? null;
        $payment->save();

        // TODO: aquí podemos:
        //  - Descontar del sueldo/semana (Expense)
        //  - Aumentar el ahorro total si aplica
        //  - Generar/actualizar evento de calendario con due_date

        $tanda->load(['members', 'payments']);

        return response()->json([
            'ok'      => true,
            'tanda'   => $tanda,
            'payment' => $payment,
        ]);
    }
}
