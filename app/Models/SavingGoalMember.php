<?php

// app/Models/SavingGoalMember.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavingGoalMember extends Model
{
    protected $fillable = [
        'saving_goal_id',
        'user_id',
        'role',
        'expected_contribution',
    ];

    public function goal(): BelongsTo
    {
        return $this->belongsTo(SavingGoal::class, 'saving_goal_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
