<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SavingGoalMovement extends Model
{
    protected $fillable = [
        'saving_goal_id',
        'user_id',
        'date',
        'amount',
        'type',
        'description',
    ];

    protected $casts = [
        'date'   => 'date',
        'amount' => 'decimal:2',
    ];

    public function goal()
    {
        return $this->belongsTo(SavingGoal::class, 'saving_goal_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
