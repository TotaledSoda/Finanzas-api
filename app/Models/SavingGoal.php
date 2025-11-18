<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\SavingGoalMember;

class SavingGoal extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'target_amount',
        'current_amount',
        'deadline',
        'category',
        'is_group',
        'status',
    ];

    protected $casts = [
        'deadline'       => 'date',
        'is_group'       => 'boolean',
        'target_amount'  => 'decimal:2',
        'current_amount' => 'decimal:2',
    ];

    // Dueño de la meta
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Miembros de la meta (relación con la tabla saving_goal_members)
    public function members()
    {
        return $this->hasMany(SavingGoalMember::class);
    }

    // Participantes (usuarios) a través de saving_goal_members
    public function participants()
    {
        return $this->belongsToMany(User::class, 'saving_goal_members')
            ->withPivot(['role', 'expected_contribution'])
            ->withTimestamps();
    }

    // Porcentaje de progreso
    public function getProgressPercentAttribute(): float
    {
        if ($this->target_amount <= 0) {
            return 0;
        }

        $percent = ($this->current_amount / $this->target_amount) * 100;

        return round(min($percent, 100), 2);
    }
}
