<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class WeeklyIncome extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'week_start',
        'week_end',
        'amount',
        'spent',
        'saved',
        'leftover',
    ];

    protected $casts = [
        'week_start' => 'date',
        'week_end'   => 'date',
        'amount'     => 'decimal:2',
        'spent'      => 'decimal:2',
        'saved'      => 'decimal:2',
        'leftover'   => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }
}
