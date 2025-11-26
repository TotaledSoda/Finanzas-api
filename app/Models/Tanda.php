<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\FinancialEvent;

class Tanda extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',             // dueño / organizador
        'name',
        'contribution_amount',
        'total_amount',
        'rounds_total',
        'start_date',
        'frequency',
        'current_round',
        'status',
        'next_payment_date',
    ];

    protected $casts = [
        'contribution_amount' => 'decimal:2',
        'total_amount'        => 'decimal:2',
        'start_date'          => 'date',
        'next_payment_date'   => 'date',
    ];

    // Dueño / organizador
    public function organizer()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Alias para que $tanda->user funcione en controllers viejos
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Registros de la tabla tanda_members
    public function members()
    {
        return $this->hasMany(TandaMember::class);
    }

    // Usuarios participantes a través de tanda_members
    public function participants()
    {
        return $this->belongsToMany(User::class, 'tanda_members')
            ->withPivot(['position', 'is_owner'])
            ->withTimestamps();
    }

    // Progreso en %
    public function getProgressPercentAttribute(): float
    {
        if ($this->rounds_total <= 0) {
            return 0;
        }

        $percent = ($this->current_round / $this->rounds_total) * 100;

        return round(min($percent, 100), 2);
    }
     public function events()
    {
        return $this->morphMany(FinancialEvent::class, 'eventable');
    }
}
