<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tanda;
use App\Models\TandaMember;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TandaController extends Controller
{
    /**
     * Lista las tandas donde el usuario es organizador o participante.
     * Filtro: ?status=active|upcoming|finished|all
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $status = $request->get('status', 'active');

        $query = Tanda::withCount('members')
            ->where(function ($q) use ($user) {
                $q->where('organizer_id', $user->id)
                  ->orWhereHas('members', function ($q2) use ($user) {
                      $q2->where('user_id', $user->id);
                  });
            });

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $tandas = $query
            ->orderBy('status')
            ->orderBy('next_date', 'asc')
            ->get()
            ->map(fn (Tanda $tanda) => $this->transformTanda($tanda, $user->id));

        return response()->json($tandas);
    }

    /**
     * Crea una nueva tanda.
     * Opcional: recibir lista de miembros.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name'                => ['required', 'string', 'max:255'],
            'description'         => ['nullable', 'string'],
            'total_amount'        => ['required', 'numeric', 'min:0'],
            'contribution_amount' => ['required', 'numeric', 'min:0'],
            'total_rounds'        => ['required', 'integer', 'min:1'],
            'start_date'          => ['required', 'date'],
            'frequency'           => ['nullable', 'string', 'in:weekly,biweekly,monthly'],

            // miembros opcionales
            'members'             => ['sometimes', 'array'],
            'members.*.name'      => ['required_with:members', 'string', 'max:255'],
            'members.*.email'     => ['nullable', 'email'],
            'members.*.phone'     => ['nullable', 'string', 'max:30'],
            'members.*.round_number' => ['nullable', 'integer', 'min:1'],
        ]);

        $data['frequency'] = $data['frequency'] ?? 'monthly';

        return DB::transaction(function () use ($data, $user) {
            // Determinar estado según fecha de inicio
            $start = Carbon::parse($data['start_date']);
            $today = Carbon::today();

            $status = $start->gt($today) ? 'upcoming' : 'active';

            $tanda = Tanda::create([
                'organizer_id'        => $user->id,
                'name'                => $data['name'],
                'description'         => $data['description'] ?? null,
                'total_amount'        => $data['total_amount'],
                'contribution_amount' => $data['contribution_amount'],
                'total_rounds'        => $data['total_rounds'],
                'current_round'       => 1,
                'start_date'          => $start->toDateString(),
                'next_date'           => $start->toDateString(),
                'frequency'           => $data['frequency'],
                'status'              => $status,
            ]);

            // Crear miembros si vienen
            if (! empty($data['members'])) {
                foreach ($data['members'] as $memberData) {
                    TandaMember::create([
                        'tanda_id'     => $tanda->id,
                        'user_id'      => null, // luego puedes ligarlo a usuarios de la app
                        'name'         => $memberData['name'],
                        'email'        => $memberData['email'] ?? null,
                        'phone'        => $memberData['phone'] ?? null,
                        'round_number' => $memberData['round_number'] ?? null,
                        'has_collected'=> false,
                    ]);
                }
            } else {
                // Si no se mandan miembros, al menos agregamos al organizador como miembro
                TandaMember::create([
                    'tanda_id'     => $tanda->id,
                    'user_id'      => $user->id,
                    'name'         => $user->name,
                    'email'        => $user->email,
                    'phone'        => null,
                    'round_number' => 1,
                    'has_collected'=> false,
                ]);
            }

            $tanda->loadCount('members');

            return response()->json(
                $this->transformTanda($tanda, $user->id),
                201
            );
        });
    }

    /**
     * Muestra detalle de una tanda (incluye miembros).
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();

        $tanda = Tanda::with('members')
            ->where(function ($q) use ($user) {
                $q->where('organizer_id', $user->id)
                  ->orWhereHas('members', function ($q2) use ($user) {
                      $q2->where('user_id', $user->id);
                  });
            })
            ->findOrFail($id);

        $data = $this->transformTanda($tanda, $user->id);

        // Agregar miembros listados (para pantalla de detalle)
        $data['members'] = $tanda->members->map(function (TandaMember $m) {
            return [
                'id'           => $m->id,
                'name'         => $m->name,
                'email'        => $m->email,
                'phone'        => $m->phone,
                'round_number' => $m->round_number,
                'has_collected'=> $m->has_collected,
            ];
        })->values();

        return response()->json($data);
    }

    /**
     * Da el formato para la UI de "Mis Tandas".
     */
    protected function transformTanda(Tanda $tanda, int $currentUserId): array
    {
        $userIsOrganizer = $tanda->organizer_id === $currentUserId;

        // Para las cards:
        // - Organizador → "Próximo pago"
        // - Participante → "Próximo cobro"
        $next_label = $userIsOrganizer ? 'Próximo pago' : 'Próximo cobro';

        return [
            'id'                  => $tanda->id,
            'name'                => $tanda->name,
            'description'         => $tanda->description,
            'role'                => $userIsOrganizer ? 'organizer' : 'participant',

            'total_amount'        => (float) $tanda->total_amount,
            'contribution_amount' => (float) $tanda->contribution_amount,

            'total_rounds'        => $tanda->total_rounds,
            'current_round'       => $tanda->current_round,
            'progress_percent'    => $tanda->progress_percent, // 3/12 → 25, etc.

            'start_date'          => $tanda->start_date?->toDateString(),
            'next_date'           => $tanda->next_date?->toDateString(),
            'next_label'          => $next_label,  // "Próximo pago" / "Próximo cobro"

            'frequency'           => $tanda->frequency,
            'status'              => $tanda->status,

            'members_count'       => $tanda->members_count ?? ($tanda->members?->count() ?? 0),

            'created_at'          => $tanda->created_at?->toAtomString(),
            'updated_at'          => $tanda->updated_at?->toAtomString(),
        ];
    }
}
