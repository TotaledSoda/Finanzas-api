<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SavingGoal;
use Illuminate\Http\Request;

class SavingGoalController extends Controller
{
    /**
     * Lista las metas de ahorro del usuario autenticado.
     * Soporta filtros: ?status=active|completed|all, ?type=group|personal
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = SavingGoal::where('user_id', $user->id);

        // Filtro por estado
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        } else {
            $query->whereIn('status', ['active', 'completed']);
        }

        // Filtro por tipo (grupales / personales)
        if ($request->filled('type')) {
            if ($request->type === 'group') {
                $query->where('is_group', true);
            } elseif ($request->type === 'personal') {
                $query->where('is_group', false);
            }
        }

        $goals = $query
            ->orderBy('deadline', 'asc')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function (SavingGoal $goal) {
                return $this->transformGoal($goal);
            });

        return response()->json($goals);
    }

    /**
     * Crea una nueva meta de ahorro.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'           => ['required', 'string', 'max:255'],
            'description'    => ['nullable', 'string'],
            'target_amount'  => ['required', 'numeric', 'min:0'],
            'current_amount' => ['nullable', 'numeric', 'min:0'],
            'deadline'       => ['nullable', 'date'],
            'category'       => ['nullable', 'string', 'max:50'],
            'is_group'       => ['nullable', 'boolean'],
        ]);

        $data['user_id'] = $request->user()->id;
        $data['current_amount'] = $data['current_amount'] ?? 0;
        $data['is_group'] = $data['is_group'] ?? false;
        $data['status'] = $data['current_amount'] >= $data['target_amount']
            ? 'completed'
            : 'active';

        $goal = SavingGoal::create($data);

        return response()->json(
            $this->transformGoal($goal),
            201
        );
    }

    /**
     * Muestra una meta específica del usuario.
     */
    public function show(Request $request, $id)
    {
        $goal = SavingGoal::where('user_id', $request->user()->id)
            ->findOrFail($id);

        return response()->json($this->transformGoal($goal));
    }

    /**
     * Actualiza una meta de ahorro.
     */
    public function update(Request $request, $id)
    {
        $goal = SavingGoal::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $data = $request->validate([
            'name'           => ['sometimes', 'string', 'max:255'],
            'description'    => ['sometimes', 'nullable', 'string'],
            'target_amount'  => ['sometimes', 'numeric', 'min:0'],
            'current_amount' => ['sometimes', 'numeric', 'min:0'],
            'deadline'       => ['sometimes', 'nullable', 'date'],
            'category'       => ['sometimes', 'nullable', 'string', 'max:50'],
            'is_group'       => ['sometimes', 'boolean'],
            'status'         => ['sometimes', 'string', 'in:active,completed,cancelled'],
        ]);

        $goal->fill($data);

        // Recalcular status si se modifica current o target
        if (array_key_exists('current_amount', $data) || array_key_exists('target_amount', $data)) {
            if ($goal->current_amount >= $goal->target_amount && $goal->target_amount > 0) {
                $goal->status = 'completed';
            } elseif ($goal->status === 'completed' && $goal->current_amount < $goal->target_amount) {
                $goal->status = 'active';
            }
        }

        $goal->save();

        return response()->json($this->transformGoal($goal));
    }

    /**
     * Helper para dar el formato que necesita la app.
     */
    protected function transformGoal(SavingGoal $goal): array
    {
        return [
            'id'              => $goal->id,
            'name'            => $goal->name,
            'description'     => $goal->description,
            'target_amount'   => (float) $goal->target_amount,
            'current_amount'  => (float) $goal->current_amount,
            'progress_percent'=> $goal->progress_percent, // accessor
            'deadline'        => optional($goal->deadline)->toDateString(),
            'category'        => $goal->category,         // para decidir icono/color en React Native
            'is_group'        => $goal->is_group,
            'status'          => $goal->status,
            'created_at'      => $goal->created_at?->toAtomString(),
            'updated_at'      => $goal->updated_at?->toAtomString(),
        ];
    }
}
