<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tanda extends Model
{
    use HasFactory;

    protected $fillable = [
        'organizer_id',
        'name',
        'description',
        'total_amount',
        'contribution_amount',
        'total_rounds',
        'current_round',
        'start_date',
        'next_date',
        'frequency',
        'status',
    ];

    protected $casts = [
        'total_amount'        => 'decimal:2',
        'contribution_amount' => 'decimal:2',
        'start_date'          => 'date',
        'next_date'           => 'date',
    ];

    public function organizer()
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    public function members()
    {
        return $this->hasMany(TandaMember::class);
    }

    // Progreso de la tanda (ej: 3/12 → 25%)
    public function getProgressPercentAttribute(): float
    {
        if ($this->total_rounds <= 0) {
            return 0;
        }

        $percent = ($this->current_round / $this->total_rounds) * 100;

        return round(min($percent, 100), 2);
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->status === 'active';
    }

    public function getIsFinishedAttribute(): bool
    {
        return $this->status === 'finished';
    }
}
