<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SavingGoal;
use App\Models\User;
use Illuminate\Http\Request;

class SavingGoalMemberController extends Controller
{
     public function index(Request $request)
    {
        $user = $request->user();

        $goals = SavingGoal::with([
                'user:id,name,email',
                'participants:id,name,email',
            ])
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id) // metas que creó
                      ->orWhereHas('participants', function ($q) use ($user) {
                          // metas donde participa
                          $q->where('user_id', $user->id);
                      });
            })
            ->orderBy('deadline', 'asc')
            ->get();

        return response()->json($goals);
    }

    public function store(Request $request, SavingGoal $goal)
    {
        $authUser = $request->user();

        // Solo el dueño de la meta puede agregar miembros
        if ($goal->user_id !== $authUser->id) {
            return response()->json([
                'message' => 'Solo el creador de la meta puede agregar participantes.',
            ], 403);
        }

        // Debe ser meta grupal
        if (! $goal->is_group) {
            return response()->json([
                'message' => 'Esta meta no es grupal.',
            ], 422);
        }

        $data = $request->validate([
            'email' => ['required', 'email'],
            'expected_contribution' => ['nullable', 'numeric', 'min:0'],
        ]);

        // Buscar usuario por correo
        $member = User::where('email', $data['email'])->first();

        if (! $member) {
            return response()->json([
                'message' => 'Usuario no encontrado. Por ahora solo puedes agregar usuarios registrados.',
            ], 404);
        }

        // Attach/update en pivot
        $goal->participants()->syncWithoutDetaching([
            $member->id => [
                'role' => 'member',
                'expected_contribution' => $data['expected_contribution'] ?? null,
            ],
        ]);

        $goal->load('participants');

        return response()->json([
            'message' => 'Miembro agregado correctamente.',
            'goal'    => $goal,
        ], 201);
    }

}
