<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SavingGoal;
use App\Models\SavingGoalMovement;
use App\Models\CalendarEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SavingGoalController extends Controller
{
    /**
     * Listado de metas donde el usuario es dueño o miembro.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $goals = SavingGoal::with('owner:id,name,email')
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhereHas('participants', function ($qp) use ($user) {
                      $qp->where('user_id', $user->id);
                  });
            })
            ->orderBy('deadline', 'asc')
            ->get()
            ->map(function (SavingGoal $goal) {
                return [
                    'id'               => $goal->id,
                    'name'             => $goal->name,
                    'description'      => $goal->description,
                    'target_amount'    => (float) $goal->target_amount,
                    'current_amount'   => (float) $goal->current_amount,
                    'progress_percent' => $goal->progress_percent,
                    'deadline'         => optional($goal->deadline)->toDateString(),
                    'category'         => $goal->category,
                    'status'           => $goal->status,
                    'is_group'         => (bool) $goal->is_group,
                ];
            });

        return response()->json($goals);
    }

    /**
     * Crear meta de ahorro.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name'          => ['required', 'string', 'max:255'],
            'description'   => ['nullable', 'string'],
            'target_amount' => ['required', 'numeric', 'min:0.01'],
            'deadline'      => ['nullable', 'date'],
            'category'      => ['nullable', 'string', 'max:100'],
            'is_group'      => ['sometimes', 'boolean'],
        ]);

        $goal = SavingGoal::create([
            'user_id'        => $user->id,
            'name'           => $data['name'],
            'description'    => $data['description'] ?? null,
            'target_amount'  => $data['target_amount'],
            'current_amount' => 0,
            'deadline'       => $data['deadline'] ?? null,
            'category'       => $data['category'] ?? null,
            'is_group'       => $data['is_group'] ?? false,
            'status'         => 'active',
        ]);

        // Opcional: dueñ@ como miembro owner en saving_goal_members
        $goal->participants()->syncWithoutDetaching([
            $user->id => [
                'role' => 'owner',
                'expected_contribution' => null,
            ],
        ]);

        return response()->json([
            'id'               => $goal->id,
            'name'             => $goal->name,
            'description'      => $goal->description,
            'target_amount'    => (float) $goal->target_amount,
            'current_amount'   => (float) $goal->current_amount,
            'progress_percent' => $goal->progress_percent,
            'deadline'         => optional($goal->deadline)->toDateString(),
            'category'         => $goal->category,
            'status'           => $goal->status,
            'is_group'         => (bool) $goal->is_group,
        ], 201);
    }

    /**
     * Aportar dinero a una meta (se conecta con ahorro total + calendario).
     */
    public function addContribution(Request $request, SavingGoal $savingGoal)
    {
        $user = $request->user();

        // dueño o miembro
        $isOwner = $savingGoal->user_id === $user->id;
        $isMember = $savingGoal->participants()
            ->where('users.id', $user->id)
            ->exists();

        if (! $isOwner && ! $isMember) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $data = $request->validate([
            'amount'      => ['required', 'numeric', 'min:0.01'],
            'date'        => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $amount = (float) $data['amount'];
        $date   = $data['date'] ?? now()->toDateString();

        DB::transaction(function () use ($savingGoal, $user, $amount, $date, $data) {

            // 1) Incrementar current_amount → esto impacta en "ahorro total" del dashboard
            $savingGoal->increment('current_amount', $amount);

            // 2) Registrar movimiento de ahorro
            $movement = $savingGoal->movements()->create([
                'user_id'     => $user->id,
                'date'        => $date,
                'amount'      => $amount,
                'type'        => 'deposit',
                'description' => $data['description'] ?? null,
            ]);

            // 3) Crear evento en calendario como gasto de ese día
            CalendarEvent::create([
                'user_id'     => $user->id,
                'date'        => $date,
                'title'       => 'Ahorro: ' . $savingGoal->name,
                'type'        => 'saving_goal',  // importante para el front
                'amount'      => $amount,        // se considera como gasto desde el sueldo
                'source_type' => SavingGoal::class,
                'source_id'   => $savingGoal->id,
                'metadata'    => [
                    'movement_id' => $movement->id,
                ],
            ]);
        });

        $savingGoal->refresh();

        return response()->json([
            'id'               => $savingGoal->id,
            'name'             => $savingGoal->name,
            'description'      => $savingGoal->description,
            'target_amount'    => (float) $savingGoal->target_amount,
            'current_amount'   => (float) $savingGoal->current_amount,
            'progress_percent' => $savingGoal->progress_percent,
            'deadline'         => optional($savingGoal->deadline)->toDateString(),
            'category'         => $savingGoal->category,
            'status'           => $savingGoal->status,
            'is_group'         => (bool) $savingGoal->is_group,
        ]);
    }
}
