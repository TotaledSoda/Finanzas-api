<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tanda;
use Illuminate\Http\Request;

class TandaController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Tanda::with([
                'user:id,name,email',
                'participants:id,name,email',
            ])
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhereHas('participants', function ($qp) use ($user) {
                      $qp->where('user_id', $user->id);
                  });
            });

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $tandas = $query
            ->orderBy('start_date', 'desc')
            ->get()
            ->map(function (Tanda $tanda) use ($user) {
                return $this->transformTanda($tanda, $user->id);
            });

        return response()->json($tandas);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name'                => ['required', 'string', 'max:255'],
            'description'         => ['nullable', 'string'],
            'contribution_amount' => ['required', 'numeric', 'min:0.01'],
            'rounds_total'        => ['required', 'integer', 'min:2'],
            'start_date'          => ['required', 'date'],
            'frequency'           => ['required', 'string', 'in:weekly,biweekly,monthly'],
        ]);

        // monto total = aporte * número de rondas
        $data['total_amount'] = $data['contribution_amount'] * $data['rounds_total'];
        $data['user_id']      = $user->id;
        $data['current_round'] = 1;
        $data['status']        = 'active';

        // por ahora next_payment_date = start_date
        $data['next_payment_date'] = $data['start_date'];

        $tanda = Tanda::create($data);

        // Agregamos al dueño como miembro
        $tanda->participants()->attach($user->id, [
            'position' => 1,
            'is_owner' => true,
        ]);

        $tanda->load(['user:id,name,email', 'participants:id,name,email']);

        return response()->json(
            $this->transformTanda($tanda, $user->id),
            201
        );
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();

        $tanda = Tanda::with([
                'user:id,name,email',
                'participants:id,name,email',
            ])
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhereHas('participants', function ($qp) use ($user) {
                      $qp->where('user_id', $user->id);
                  });
            })
            ->findOrFail($id);

        return response()->json($this->transformTanda($tanda, $user->id));
    }

    protected function transformTanda(Tanda $tanda, int $currentUserId): array
    {
        $role = $tanda->user_id === $currentUserId ? 'owner' : 'member';

        return [
            'id'                   => $tanda->id,
            'name'                 => $tanda->name,
            'description'          => $tanda->description,
            'role'                 => $role,
            'total_amount'         => (float) $tanda->total_amount,
            'contribution_amount'  => (float) $tanda->contribution_amount,
            'rounds_total'         => $tanda->rounds_total,
            'current_round'        => $tanda->current_round,
            'progress_percent'     => $tanda->progress_percent,
            'start_date'           => optional($tanda->start_date)->toDateString(),
            'next_payment_date'    => optional($tanda->next_payment_date)->toDateString(),
            'frequency'            => $tanda->frequency,
            'status'               => $tanda->status,
            'owner'                => $tanda->user ? [
                'id'    => $tanda->user->id,
                'name'  => $tanda->user->name,
                'email' => $tanda->user->email,
            ] : null,
            'participants'         => $tanda->participants->map(function ($user) {
                return [
                    'id'       => $user->id,
                    'name'     => $user->name,
                    'email'    => $user->email,
                    'position' => $user->pivot->position,
                    'is_owner' => (bool) $user->pivot->is_owner,
                ];
            })->values(),
            'created_at'           => $tanda->created_at?->toAtomString(),
            'updated_at'           => $tanda->updated_at?->toAtomString(),
        ];
    }
}
