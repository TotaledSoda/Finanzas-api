<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
        'deadline'   => 'date',
        'is_group'   => 'boolean',
        'target_amount'  => 'decimal:2',
        'current_amount' => 'decimal:2',
    ];

    // Relación con el usuario
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Accessor para porcentaje (no se guarda en BD, se calcula)
    public function getProgressPercentAttribute(): float
    {
        if ($this->target_amount <= 0) {
            return 0;
        }

        $percent = ($this->current_amount / $this->target_amount) * 100;

        return round(min($percent, 100), 2);
    }
}
