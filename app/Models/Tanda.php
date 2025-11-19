<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tanda extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'total_amount',
        'contribution_amount',
        'rounds_total',
        'current_round',
        'start_date',
        'next_payment_date',
        'frequency',
        'status',
    ];

    protected $casts = [
        'total_amount'        => 'decimal:2',
        'contribution_amount' => 'decimal:2',
        'start_date'          => 'date',
        'next_payment_date'   => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function members()
    {
        return $this->hasMany(TandaMember::class);
    }

    public function participants()
    {
        return $this->belongsToMany(User::class, 'tanda_members')
            ->withPivot(['position', 'is_owner'])
            ->withTimestamps();
    }

    // porcentaje de progreso basado en la ronda actual
    public function getProgressPercentAttribute(): float
    {
        if ($this->rounds_total <= 0) {
            return 0;
        }

        $percent = ($this->current_round / $this->rounds_total) * 100;

        return round(min($percent, 100), 2);
    }
}
