<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];
    public function sharedSavingGoals()
{
    return $this->belongsToMany(SavingGoal::class, 'saving_goal_members')
        ->withPivot(['role', 'expected_contribution'])
        ->withTimestamps();
}
public function weeklyIncomes()
{
    return $this->hasMany(WeeklyIncome::class);
}

public function expenses()
{
    return $this->hasMany(Expense::class);
}

}
